<?php

declare(strict_types=1);

if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    fwrite(STDERR, "Run this fixture as the web user: docker exec --user www-data ...\n");
    exit(2);
}

require '/var/www/html/config/config.inc.php';

Shop::setContext(Shop::CONTEXT_SHOP, 1);
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
$context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
$context->cart = new Cart();
$context->cart->id_shop = (int) $context->shop->id;
$context->cart->id_lang = (int) $context->language->id;
$context->cart->id_currency = (int) $context->currency->id;

const PRODUCT_PREFIX = 'NPBENCH-';
const CATEGORY_PREFIX = 'Benchmark ';
const ATTRIBUTE_GROUP_NAMES = ['Benchmark size', 'Benchmark color'];

function output(array $value): never
{
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

function translated(string $value): array
{
    $result = [];
    foreach (Language::getIDs(false) as $idLang) {
        $result[(int) $idLang] = $value;
    }

    return $result;
}

function removeFixtureFromFacetTemplates(array $categoryRows, array $groupRows): void
{
    $categoryIds = array_map(static fn (array $row): int => (int) $row['id_category'], $categoryRows);
    $groupIds = array_map(static fn (array $row): int => (int) $row['id_attribute_group'], $groupRows);
    if ($categoryIds === [] && $groupIds === []) {
        return;
    }

    $templates = Db::getInstance()->executeS('SELECT id_layered_filter, filters FROM `' . _DB_PREFIX_ . 'layered_filter`');
    foreach ($templates as $template) {
        $filters = Tools::unSerialize((string) $template['filters']);
        if (!is_array($filters)) {
            continue;
        }
        $filters['categories'] = array_values(array_filter(
            $filters['categories'] ?? [],
            static fn ($idCategory): bool => !in_array((int) $idCategory, $categoryIds, true)
        ));
        foreach ($groupIds as $groupId) {
            unset($filters['layered_selection_ag_' . $groupId]);
        }
        Db::getInstance()->update(
            'layered_filter',
            [
                'filters' => serialize($filters),
                'n_categories' => count($filters['categories']),
            ],
            'id_layered_filter = ' . (int) $template['id_layered_filter']
        );
    }
}

function deleteExistingFixture(): void
{
    $productIds = Db::getInstance()->executeS(
        "SELECT id_product FROM `" . _DB_PREFIX_ . "product` WHERE reference LIKE '" . pSQL(PRODUCT_PREFIX) . "%'"
    );
    foreach ($productIds as $row) {
        $product = new Product((int) $row['id_product']);
        if (Validate::isLoadedObject($product) && !$product->delete()) {
            throw new RuntimeException('Unable to delete existing fixture product ' . (int) $row['id_product']);
        }
    }

    $categoryIds = Db::getInstance()->executeS(
        "SELECT DISTINCT cl.id_category FROM `" . _DB_PREFIX_ . "category_lang` cl " .
        "WHERE cl.name LIKE '" . pSQL(CATEGORY_PREFIX) . "%'"
    );
    usort(
        $categoryIds,
        static fn (array $left, array $right): int => (int) $right['id_category'] <=> (int) $left['id_category']
    );
    foreach ($categoryIds as $row) {
        $category = new Category((int) $row['id_category']);
        if (Validate::isLoadedObject($category) && !$category->delete()) {
            throw new RuntimeException('Unable to delete existing fixture category ' . (int) $row['id_category']);
        }
    }

    $groupIds = Db::getInstance()->executeS(
        "SELECT DISTINCT agl.id_attribute_group FROM `" . _DB_PREFIX_ . "attribute_group_lang` agl " .
        "WHERE agl.name IN ('Benchmark size', 'Benchmark color')"
    );
    removeFixtureFromFacetTemplates($categoryIds, $groupIds);

    foreach ($groupIds as $row) {
        $group = new AttributeGroup((int) $row['id_attribute_group']);
        if (Validate::isLoadedObject($group) && !$group->delete()) {
            throw new RuntimeException('Unable to delete existing fixture attribute group ' . (int) $row['id_attribute_group']);
        }
    }

    $aliasIds = Db::getInstance()->executeS(
        "SELECT id_alias FROM `" . _DB_PREFIX_ . "alias` WHERE alias IN ('trainrs', 'sneakers', 'headphons')"
    );
    foreach ($aliasIds as $row) {
        $alias = new Alias((int) $row['id_alias']);
        if (Validate::isLoadedObject($alias)) {
            $alias->delete();
        }
    }
}

function createCategory(string $name): Category
{
    $category = new Category();
    $category->id_parent = (int) Configuration::get('PS_HOME_CATEGORY');
    $category->active = true;
    $category->name = translated($name);
    $category->link_rewrite = translated(Tools::str2url($name));
    if (!$category->add()) {
        throw new RuntimeException('Unable to create category ' . $name);
    }

    return $category;
}

function createProduct(array $input, array $categoryIds): Product
{
    $product = new Product();
    $product->id_shop_default = 1;
    $product->id_category_default = (int) $categoryIds[0];
    $product->id_tax_rules_group = 0;
    $product->reference = $input['reference'];
    $product->name = translated($input['name']);
    $product->link_rewrite = translated(Tools::str2url($input['name']));
    $product->description_short = translated($input['description']);
    $product->description = translated($input['description']);
    $product->meta_title = translated($input['name']);
    $product->price = (float) $input['price'];
    $product->wholesale_price = 0;
    $product->active = (bool) ($input['active'] ?? true);
    $product->visibility = $input['visibility'] ?? 'both';
    $product->available_for_order = true;
    $product->show_price = true;
    $product->online_only = false;
    $product->minimal_quantity = 1;
    $product->condition = 'new';
    $product->indexed = false;
    $product->redirect_type = '404';

    if (!$product->add()) {
        throw new RuntimeException('Unable to create product ' . $input['reference']);
    }
    if (!$product->addToCategories(array_map('intval', $categoryIds))) {
        throw new RuntimeException('Unable to associate categories for ' . $input['reference']);
    }
    StockAvailable::setProductOutOfStock((int) $product->id, 0, 1);
    StockAvailable::setQuantity((int) $product->id, 0, (int) $input['quantity'], 1);

    return $product;
}

function createCombinationFixture(Product $product): array
{
    $groups = [];
    $attributes = [];
    foreach (
        [
            'Benchmark size' => ['Small', 'Large'],
            'Benchmark color' => ['Blue', 'Red'],
        ] as $groupPosition => $definition
    ) {
        $groupName = (string) $groupPosition;
        $group = new AttributeGroup();
        $group->name = translated($groupName);
        $group->public_name = translated($groupName);
        $group->group_type = 'select';
        $group->position = count($groups);
        if (!$group->add()) {
            throw new RuntimeException('Unable to create fixture attribute group ' . $groupName);
        }
        $groups[$groupName] = (int) $group->id;
        foreach ($definition as $position => $label) {
            $attribute = new ProductAttribute();
            $attribute->id_attribute_group = (int) $group->id;
            $attribute->name = translated($label);
            $attribute->position = $position;
            if (!$attribute->add()) {
                throw new RuntimeException('Unable to create fixture attribute ' . $label);
            }
            $attributes[$groupName][$label] = (int) $attribute->id;
        }
    }

    $combinations = [];
    foreach (
        [
            ['size' => 'Small', 'color' => 'Blue', 'reference' => 'NPBENCH-TEE-S', 'quantity' => 4, 'default' => true],
            ['size' => 'Small', 'color' => 'Red', 'reference' => 'NPBENCH-TEE-S-RED', 'quantity' => 3, 'default' => false],
            ['size' => 'Large', 'color' => 'Blue', 'reference' => 'NPBENCH-TEE-L-BLUE', 'quantity' => 2, 'default' => false],
            ['size' => 'Large', 'color' => 'Red', 'reference' => 'NPBENCH-TEE-L', 'quantity' => 0, 'default' => false],
        ] as $definition
    ) {
        $combination = new Combination();
        $combination->id_product = (int) $product->id;
        $combination->reference = $definition['reference'];
        $combination->minimal_quantity = 1;
        $combination->price = 0;
        $combination->weight = 0;
        $combination->default_on = $definition['default'];
        if (
            !$combination->add()
            || !$combination->setAttributes([
                $attributes['Benchmark size'][$definition['size']],
                $attributes['Benchmark color'][$definition['color']],
            ])
        ) {
            throw new RuntimeException('Unable to create fixture combination ' . $definition['reference']);
        }
        StockAvailable::setQuantity(
            (int) $product->id,
            (int) $combination->id,
            (int) $definition['quantity'],
            1
        );
        $combinations[] = [
            'id' => (int) $combination->id,
            'label' => $definition['size'] . ' / ' . $definition['color'],
            'reference' => $definition['reference'],
            'quantity' => (int) $definition['quantity'],
        ];
    }
    Product::updateDefaultAttribute((int) $product->id);

    return [
        'groups' => $groups,
        'combinations' => $combinations,
    ];
}

function addAlias(string $aliasText, string $searchText): void
{
    $alias = new Alias(null, $aliasText, $searchText);
    $alias->active = true;
    if (!$alias->add()) {
        throw new RuntimeException('Unable to add search alias ' . $aliasText);
    }
}

function configureFacetTemplate(array $categoryIds, array $groupIds): array
{
    $template = Db::getInstance()->getRow(
        'SELECT id_layered_filter, filters FROM `' . _DB_PREFIX_ . 'layered_filter` ORDER BY date_add DESC'
    );
    if (!$template) {
        throw new RuntimeException('Default ps_facetedsearch template not found');
    }

    $filters = Tools::unSerialize((string) $template['filters']);
    if (!is_array($filters)) {
        throw new RuntimeException('Unable to decode ps_facetedsearch template');
    }
    $filters['categories'] = array_values(array_unique(array_merge(
        array_map('intval', $filters['categories'] ?? []),
        array_map('intval', $categoryIds)
    )));
    foreach ($groupIds as $groupId) {
        $filters['layered_selection_ag_' . (int) $groupId] = [
            'filter_type' => 0,
            'filter_show_limit' => 0,
        ];
    }
    Db::getInstance()->update(
        'layered_filter',
        [
            'filters' => serialize($filters),
            'n_categories' => count($filters['categories']),
        ],
        'id_layered_filter = ' . (int) $template['id_layered_filter']
    );

    $module = Module::getInstanceByName('ps_facetedsearch');
    if (!$module || !method_exists($module, 'buildLayeredCategories')) {
        throw new RuntimeException('ps_facetedsearch module is unavailable');
    }
    $module->indexAttributeGroup();
    $module->indexAttributes();
    $module->fullPricesIndexProcess();
    $module->buildLayeredCategories();

    return [
        'template_id' => (int) $template['id_layered_filter'],
        'categories' => array_map('intval', $categoryIds),
        'attribute_groups' => array_map('intval', $groupIds),
    ];
}

function seed(): never
{
    $context = Context::getContext();

    deleteExistingFixture();

    $categories = [];
    foreach (['Benchmark Footwear', 'Benchmark Audio', 'Benchmark Accessories', 'Benchmark Outdoor'] as $name) {
        $category = createCategory($name);
        $categories[$name] = (int) $category->id;
    }

    $definitions = [
        [
            'name' => 'Benchmark Trail Trainers',
            'reference' => 'NPBENCH-TRAIL-001',
            'description' => 'Synthetic trail footwear fixture for exact term, alias, stock and category search checks.',
            'price' => 79.00,
            'quantity' => 10,
            'categories' => ['Benchmark Footwear', 'Benchmark Outdoor'],
        ],
        [
            'name' => 'Benchmark Studio Headphones',
            'reference' => 'NPBENCH-AUDIO-001',
            'description' => 'Synthetic audio fixture for exact term and typo alias checks.',
            'price' => 129.00,
            'quantity' => 5,
            'categories' => ['Benchmark Audio'],
        ],
        [
            'name' => 'Benchmark Café Mug',
            'reference' => 'NPBENCH-MUG-001',
            'description' => 'Synthetic accented-name fixture for search normalization checks.',
            'price' => 12.50,
            'quantity' => 20,
            'categories' => ['Benchmark Accessories'],
        ],
        [
            'name' => 'Benchmark Canvas Bag',
            'reference' => 'NPBENCH-BAG-001',
            'description' => 'Synthetic accessories fixture for category and result-set checks.',
            'price' => 35.00,
            'quantity' => 8,
            'categories' => ['Benchmark Accessories', 'Benchmark Outdoor'],
        ],
        [
            'name' => 'Benchmark Combination Tee',
            'reference' => 'NPBENCH-TEE-BASE',
            'description' => 'Synthetic combination fixture with one in-stock and one out-of-stock size.',
            'price' => 24.00,
            'quantity' => 0,
            'categories' => ['Benchmark Accessories'],
            'combinations' => true,
        ],
        [
            'name' => 'Benchmark Out of Stock Lamp',
            'reference' => 'NPBENCH-LAMP-001',
            'description' => 'Synthetic out-of-stock fixture.',
            'price' => 44.00,
            'quantity' => 0,
            'categories' => ['Benchmark Accessories'],
        ],
        [
            'name' => 'Benchmark Hidden Telescope',
            'reference' => 'NPBENCH-HIDDEN-001',
            'description' => 'Synthetic disabled fixture that must not appear in storefront search.',
            'price' => 199.00,
            'quantity' => 3,
            'active' => false,
            'visibility' => 'none',
            'categories' => ['Benchmark Outdoor'],
        ],
    ];

    $products = [];
    $attributeGroups = [];
    $combinations = [];
    foreach ($definitions as $definition) {
        $categoryIds = array_map(fn (string $name): int => $categories[$name], $definition['categories']);
        $product = createProduct($definition, $categoryIds);
        $products[$definition['reference']] = [
            'id' => (int) $product->id,
            'name' => $definition['name'],
            'active' => (bool) $product->active,
            'visibility' => $product->visibility,
            'quantity' => (int) $definition['quantity'],
        ];
        if (!empty($definition['combinations'])) {
            $combinationFixture = createCombinationFixture($product);
            $attributeGroups = $combinationFixture['groups'];
            $combinations = $combinationFixture['combinations'];
        }
    }

    addAlias('sneakers', 'trainers');

    $facetTemplate = configureFacetTemplate(array_values($categories), array_values($attributeGroups));

    $started = microtime(true);
    Search::indexation(true);
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    Tools::clearAllCache();

    output([
        'action' => 'seed',
        'prestashop' => _PS_VERSION_,
        'php' => PHP_VERSION,
        'shop' => (int) $context->shop->id,
        'language' => $context->language->iso_code,
        'currency' => $context->currency->iso_code,
        'categories' => $categories,
        'products' => $products,
        'attribute_groups' => $attributeGroups,
        'combinations' => $combinations,
        'facet_template' => $facetTemplate,
        'aliases' => [
            'sneakers' => 'trainers',
        ],
        'full_reindex_ms' => $durationMs,
    ]);
}

function probe(string $query): never
{
    $result = Search::find(
        (int) Context::getContext()->language->id,
        $query,
        1,
        50,
        'position',
        'desc',
        false,
        false,
        Context::getContext()
    );
    $products = [];
    foreach (($result['result'] ?? []) as $row) {
        if (str_starts_with((string) ($row['reference'] ?? ''), PRODUCT_PREFIX)) {
            $products[] = [
                'id' => (int) $row['id_product'],
                'reference' => (string) $row['reference'],
                'name' => (string) $row['name'],
                'price' => (float) $row['price'],
                'quantity' => (int) $row['quantity'],
                'link' => (string) $row['link'],
            ];
        }
    }
    output([
        'action' => 'probe',
        'query' => $query,
        'total' => (int) ($result['total'] ?? 0),
        'fixture_products' => $products,
    ]);
}

function productByReference(string $reference): Product
{
    $id = (int) Db::getInstance()->getValue(
        "SELECT id_product FROM `" . _DB_PREFIX_ . "product` WHERE reference = '" . pSQL($reference) . "'"
    );
    $product = new Product($id);
    if (!Validate::isLoadedObject($product)) {
        throw new RuntimeException('Fixture product not found: ' . $reference);
    }

    return $product;
}

function mutate(): never
{
    $product = productByReference('NPBENCH-BAG-001');
    $categoryId = (int) Db::getInstance()->getValue(
        "SELECT cl.id_category FROM `" . _DB_PREFIX_ . "category_lang` cl " .
        "WHERE cl.name = 'Benchmark Audio' AND cl.id_lang = " . (int) Context::getContext()->language->id
    );
    if ($categoryId < 1) {
        throw new RuntimeException('Benchmark Audio category not found');
    }
    $product->name = translated('Benchmark Updated Travel Satchel');
    $product->link_rewrite = translated('benchmark-updated-travel-satchel');
    $product->price = 39.00;
    $product->id_category_default = $categoryId;
    $product->indexed = false;
    if (!$product->update()) {
        throw new RuntimeException('Unable to mutate fixture product');
    }
    if (!$product->updateCategories([$categoryId])) {
        throw new RuntimeException('Unable to move fixture product category');
    }
    StockAvailable::setQuantity((int) $product->id, 0, 6, 1);

    output([
        'action' => 'mutate',
        'id' => (int) $product->id,
        'reference' => $product->reference,
        'name' => $product->name[(int) Context::getContext()->language->id],
        'price' => (float) $product->price,
        'quantity' => 6,
        'category_id' => $categoryId,
        'category' => 'Benchmark Audio',
        'indexed' => (bool) $product->indexed,
    ]);
}

function reindexProduct(): never
{
    $product = productByReference('NPBENCH-BAG-001');
    $started = microtime(true);
    Search::indexation(false, (int) $product->id);
    $module = Module::getInstanceByName('ps_facetedsearch');
    if ($module && method_exists($module, 'indexProductPrices')) {
        $module->indexAttributes((int) $product->id);
        $module->indexProductPrices((int) $product->id);
        $module->buildLayeredCategories();
    }
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    Tools::clearAllCache();
    output([
        'action' => 'reindex-product',
        'id' => (int) $product->id,
        'reference' => $product->reference,
        'duration_ms' => $durationMs,
    ]);
}

function clearIndex(): never
{
    $product = productByReference('NPBENCH-AUDIO-001');
    Db::getInstance()->delete('search_index', 'id_product = ' . (int) $product->id);
    Db::getInstance()->update('product', ['indexed' => 0], 'id_product = ' . (int) $product->id);
    Db::getInstance()->update('product_shop', ['indexed' => 0], 'id_product = ' . (int) $product->id);
    Tools::clearAllCache();
    output([
        'action' => 'clear-index',
        'id' => (int) $product->id,
        'reference' => $product->reference,
    ]);
}

function restoreIndex(): never
{
    $product = productByReference('NPBENCH-AUDIO-001');
    $started = microtime(true);
    Search::indexation(false, (int) $product->id);
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    Tools::clearAllCache();
    output([
        'action' => 'restore-index',
        'id' => (int) $product->id,
        'reference' => $product->reference,
        'duration_ms' => $durationMs,
    ]);
}

function fullReindex(): never
{
    $memoryBefore = memory_get_usage(true);
    $started = microtime(true);
    Search::indexation(true);
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    $memoryAfter = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    Tools::clearAllCache();
    output([
        'action' => 'full-reindex',
        'duration_ms' => $durationMs,
        'process_memory_before_bytes' => $memoryBefore,
        'process_memory_after_bytes' => $memoryAfter,
        'process_peak_memory_bytes' => $peakMemory,
    ]);
}

$action = $argv[1] ?? '';
match ($action) {
    'seed' => seed(),
    'probe' => probe($argv[2] ?? ''),
    'mutate' => mutate(),
    'reindex-product' => reindexProduct(),
    'clear-index' => clearIndex(),
    'restore-index' => restoreIndex(),
    'full-reindex' => fullReindex(),
    default => throw new InvalidArgumentException(
        'Use: seed | probe <query> | mutate | reindex-product | clear-index | restore-index | full-reindex'
    ),
};

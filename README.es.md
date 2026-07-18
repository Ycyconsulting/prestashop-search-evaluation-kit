# Kit de evaluación de búsqueda para PrestaShop

Una lista abierta y reproducible para comparar la búsqueda de una tienda PrestaShop en staging antes de cambiar el buscador de producción.

Sirve para la búsqueda nativa, un módulo autoalojado o un servicio alojado. El kit no clasifica proveedores, no predice conversión y no certifica que una tienda esté lista para producción.

**Transparencia sobre el mantenedor:** YCY Consulting and Investment SL opera Neuroplugin y vende NP Search. Ninguna fila de la lista depende de NP Search, pero la demo opcional enlazada más abajo es nuestro producto y está identificada como tal.

[English version](README.md)

## Contenido

- `checklist.csv`: 18 escenarios observables de búsqueda, indexación, storefront, operaciones y rollback.
- `SCORING.md`: un modelo de evidencia sencillo con cuatro bloqueos obligatorios.
- Una plantilla pública para proponer escenarios sin publicar credenciales, datos de clientes ni información privada de una tienda.

## Cómo usarlo

1. Clona la configuración de producción en una tienda de staging y elimina o anonimiza datos de clientes y pedidos.
2. Registra versiones exactas de PrestaShop, PHP, tema y buscador, además del tamaño del catálogo, idioma, moneda y configuración de caché.
3. Prepara un fixture pequeño y determinista: productos, combinaciones, categorías, atributos, stock, acentos, referencias, sinónimos y errores tipográficos intencionados.
4. Ejecuta el mismo fixture antes y después del cambio propuesto.
5. Marca cada fila como `pass`, `partial`, `fail` o `not_applicable`. Guarda las pruebas en tu propio sistema controlado; no publiques aquí URLs privadas, credenciales, logs ni datos de clientes.
6. Trata cualquier fallo de bloqueo obligatorio como una condición de parada hasta corregirlo y repetir la prueba.

La documentación actual de PrestaShop recomienda probar los módulos y describe pruebas unitarias, de integración y de interfaz. La [documentación del índice de PrestaShop 9](https://devdocs.prestashop-project.org/9/development/components/console/prestashop-search-index/) indica que una reconstrucción completa puede tardar en catálogos grandes y documenta opciones más limitadas por tienda y producto. La guía oficial del front office recomienda recorrer la tienda como cliente y recuerda que los temas y módulos pueden cambiar la experiencia.

Referencias oficiales:

- [Pruebas de módulos — PrestaShop 9](https://devdocs.prestashop-project.org/9/modules/testing/)
- [Comando de índice de búsqueda — PrestaShop 9](https://devdocs.prestashop-project.org/9/development/components/console/prestashop-search-index/)
- [Recorrer el front office — PrestaShop 8](https://docs.prestashop-project.org/v.8-documentation/user-guide/browsing-front-office)

## Ejemplos ejecutados

El baseline nativo es una ejecución controlada contra la búsqueda de PrestaShop 8.2.7: 15 `pass`, 1 `partial`, 0 `fail` y 2 `not_applicable`. La decisión es `inconclusive` porque no había un cambio candidato que pudiera revertirse. El resultado parcial documenta una referencia de combinación que abrió la combinación predeterminada en vez de la consultada.

[Leer el baseline de búsqueda nativa de PrestaShop 8.2.7 (en inglés)](examples/prestashop-8.2.7-native/REPORT.md)

El segundo ejemplo aplica la misma lista al ZIP comercial exacto de NP Search 2.13.4 en la misma plataforma principal: 17 `pass`, 0 `partial`, 0 `fail` y 1 `not_applicable`. Los cuatro bloqueos obligatorios pasan. Es evidencia del producto ejecutada por el mantenedor, con hash y limitaciones publicados; no es un caso independiente de un comercio.

[Leer la evaluación de NP Search 2.13.4 / PrestaShop 8.2.7 (en inglés)](examples/np-search-2.13.4-ps8.2.7/REPORT.md)

## Fixture mínimo

Incluye al menos:

- un nombre exacto de producto y una referencia/SKU;
- un término acentuado y su forma sin acento;
- un par de sinónimos aprobado;
- un error intencionado de un carácter;
- un producto con combinaciones;
- un producto con stock y otro sin stock;
- un producto oculto o desactivado;
- dos categorías y al menos dos atributos filtrables;
- un producto cuyo precio, stock, nombre y categoría cambien durante la prueba.

## Demo de proveedor: solo NP Search

Este enlace abre nuestro fixture de decisión de NP Search en el navegador, no una instancia viva del paquete ni una herramienta multiproveedor. El destino etiqueta el fixture como evidencia de la versión anterior 2.13.2; la evidencia actual 2.13.4 es el informe separado del paquete exacto enlazado arriba. El fixture contiene 12 productos ficticios y permite cambiar tolerancia a errores, sinónimos, facetas de stock/categoría y reglas pin/boost/hide; después muestra una traza simulada y una interacción de añadir al carrito. No conecta ninguna tienda y no demuestra latencia ni comportamiento en tu catálogo.

[Abrir nuestra demo exclusiva de NP Search](https://neuroplugin.com/es/prestashop-search-module?utm_source=github&utm_medium=organic-repo&utm_campaign=search-14d-launch&utm_content=es-evaluation-kit#search-decision-lab)

Repite siempre el protocolo en la tienda, tema, catálogo, infraestructura y buscador exactos que estás evaluando.

## Transparencia

El kit no presupone que un proveedor sea la respuesta correcta. El ejemplo nativo es un baseline. El ejemplo de NP Search es una prueba declarada del mantenedor sobre un paquete exacto, no evidencia comparativa independiente ni una afirmación de compatibilidad universal.

## Licencia

MIT. Consulta [LICENSE](LICENSE).

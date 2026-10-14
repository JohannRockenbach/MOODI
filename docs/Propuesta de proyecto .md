Apóstoles (Misiones), 9 de Mayo de 2025

 

Al Prof. Adjunto de las cátedras

Trabajo Final (ASC) | Proyecto Software (LSI)

FCEQyN \- UNaM

Lic. Sergio Daniel Caballero

S/D

 

De mi mayor consideración:

 

Me dirijo a Ud. a efectos de presentar la propuesta para el desarrollo de un producto software ***“Moodi” \- Sistema de gestión para restaurantes*** como tema para las cátedras “Proyecto Software” de la carrera Licenciatura en Sistemas de Información (plan de estudios 2013\) y “Trabajo Final” de la carrera Analista en Sistemas de Computación (plan de estudios 2010).

 

Sin otro particular, y quedando a la espera de la evaluación de la propuesta, me despido atte.

 

 

 

 

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_

Rockenbach Johann Tamir Emanuel

01224 \- Licenciatura en Sistemas de Información

906551 \- Analista en Sistemas de Computación

 45.053.697

 

*Reservado para el equipo de cátedra*

## **Evaluación de la propuesta**

| Nombre del proyecto |   |
| :---- | :---- |
| Alumno/a |   |
| Fecha de la evaluación |   |

 

| Aspecto | Evaluación |
| ----- | ----- |
| Presentación en general |     |
| Objetivos |     |
| Requisitos funcionales |     |
| Requisitos no funcionales |     |
| Procesos automáticos | 1\.   2\.   3\.   |
| Planificación |     |
| Estrategia de validación / verificación |     |
| Metodología |     |

 

 

| Resultado final | APROBADO \- DEBE CORREGIR |
| :---- | :---: |

 

*Firma por el equipo de cátedra:*

## **Contenidos**

 

**Evaluación de la propuesta                                                                                     	1**

**Contenidos                                                                                                                	2**

**Introducción y objetivos                                                                                          	3**

**Alcance y limitaciones                                                                                             	3**

**Especificación de módulos                                                                                     	3**

Funcionales                                                                                                           	3

No funcionales                                                                                                      	3

**Descripción de los módulos                                                                                    	3**

**Procesos automatizados                                                                                         	4**

**Estimación de tamaño por módulo                                                                         	4**

**Entorno tecnológico y metodológico                                                                     	5**

**Planificación de actividades                                                                                    	5**

 

 

 

## **Planteo del problema**

Diversos restaurantes presentan dificultades en la gestión integral de sus operaciones debido a la falta de sistemas unificados que permitan controlar en tiempo real las reservas de mesas, la toma de pedidos, el stock de insumos, el seguimiento de producción de cocina, la facturación y el control de auditoría. Estos procesos cuando se gestionan manualmente o mediante soluciones parciales, generan ineficiencia, errores en los pedidos, demoras en el servicio y pérdidas económicas por falta de control sobre el inventario o sobre ventas de productos no disponibles. Además, en establecimientos con alto volumen de clientes o con modalidad de autoservicio, como ocurre en algunas cadenas de comida rápida, se vuelve cada vez más necesario implementar soluciones que permitan al cliente interactuar directamente con el sistema desde su mesa, realizar pedidos personalizados y acceder a beneficios, sin depender completamente del personal

## **Introducción y objetivos**

 

 El presente proyecto propone el desarrollo de un sistema de gestión integral para restaurantes, adaptable a distintos tipos de establecimientos gastronómicos (comida rápida, gourmet, parrillas, hamburgueserías, entre otros). La solución surge como respuesta a la necesidad de automatizar y centralizar los procesos críticos que hacen al funcionamiento diario de un restaurante, tales como la gestión de mesas, reservas, pedidos, cocinas, stock, usuarios y auditoría.

En un contexto donde la digitalización mejora la eficiencia operativa y la experiencia del cliente, este sistema permitirá, realizar pedidos desde pantallas ubicadas en las mesas, personalizar productos, gestionar stock en función de recetas e ingredientes, controlar el acceso a usuarios con distintos roles y brindar informes parametrizables y estadísticas clave para la toma de decisiones. 

        	Objetivos del proyecto:

                    	

1\.     Automatizar la gestión de pedidos y atención al cliente

·        Desarrollar un sistema que permita al cliente realizar pedidos desde una interfaz digital (por ejemplo, pantallas en mesa o QR), incluyendo la posibilidad de personalizar productos (agregar/quitar ingredientes), calcular automáticamente el precio final, y asociar el pedido a su perfil de usuario para acceder a beneficios.

2\.     Controlar en tiempo real la disponibilidad de mesas y reservas

·        Implementar un módulo que permita al personal y/o al cliente visualizar la ocupación de mesas en tiempo real, registrar y modificar reservas, asignar mesas al llegar, y liberar automáticamente una mesa cuando se cierra la cuenta.

3\.     Gestionar y controlar el stock de insumos e ingredientes

·        Vincular cada producto con sus ingredientes y recetas, de forma que al registrar un pedido se descuente automáticamente del inventario correspondiente. Generar alertas cuando se alcance el stock mínimo e incluir sugerencias de reposición según historial de consumo.

4\.     Optimizar el flujo de trabajo en cocina

·        Diseñar un módulo de producción que reciba los pedidos automáticamente, permita a la cocina cambiar su estado (en preparación, listo, etc.), y calcule tiempos estimados. Esto busca reducir errores de comunicación y mejorar la trazabilidad del pedido.

5\.     Brindar reportes parametrizables e informes estadísticos.

·        Incorporar dashboard que permitan consultar métricas clave como ventas por producto, tiempos de atención, consumo por cliente, entre otros. Los informes deben ser configurables por el usuario y exportables para facilitar la toma de decisiones.

6\.     Implementar procesos automatizados clave.

· Desarrollar un sistema proactivo capaz de tomar decisiones para optimizar la gestión del restaurante y personalizar la experiencia del cliente. Generando promociones dinámicas, ofreciendo recomendaciones personalizadas, sugiriendo recetas alternativas o "Especiales del día".

7\.     Registrar la actividad del sistema mediante autoría.

·      Desarrollar un módulo de log que registre accesos, modificaciones, errores o eventos relevantes, permitiendo a los administradores auditar las acciones realizadas por cada usuario del sistema

 

## **Alcance y limitaciones**

**Alcance**

El sistema propuesto cubrirá de forma integral los procesos clave de operación en un restaurante, incluyendo la gestión de pedidos personalizados, reservas, stock de ingredientes, ventas presenciales y por delivery, clientes y auditoría. Estará diseñado bajo una arquitectura en capas, con base de datos relacional normalizada en tercera forma normal, roles diferenciados de usuario y funcionalidades orientadas a la automatización de tareas operativas.

 

·  ***Funcionalidades incluidas***

***Pedidos personalizados y en mesa.***

Gestión de pedidos. Los clientes podrán armar sus pedidos con ingredientes extra o excluidos, con costos ajustados automáticamente. Los pedidos se enviarán directamente a la cocina.

·        ***Reservas y gestión de mesas***

Registro manual o digital de reservas, con control de disponibilidad en tiempo real, asignación de mesas y liberación automática al cerrar la cuenta. Visualización del estado de las mesas en mapa o lista.

·        ***Stock de ingredientes y bebidas***

Control de stock por insumos, con baja automática por consumo de recetas. Alertas de bajo stock, y posibilidad de configurar productos con vencimiento.

·        ***Producción / Cocina***

Recepción de pedidos en cocina con estados (en preparación, listo, entregado). Seguimiento de tiempos de cocción y preparación.

·        ***Clientes***

Registro de clientes frecuentes, historial de consumo, asignación de promociones y beneficios. Acceso con usuario/contraseña. Acceso limitado sin login con una cuenta predeterminada.

 

·        ***Ventas y facturación***

Control de cuentas por mesa o por pedido, emisión de comprobantes, cierre de cuenta, medios de pago y propinas.

·        ***Delivery***

Registro de pedidos a domicilio, cálculo de costos según zona y seguimiento de estado. Asociación con usuarios registrados o como invitado.

·        ***Informes y estadísticas***

Generación de informes parametrizados sobre ventas, pedidos, stock, turnos, etc. Mínimo dos informes estadísticos.

·        ***Auditoría y log de eventos***

Registro automático de todas las acciones relevantes del sistema, por usuario, con fecha y hora. Incluye cambios en pedidos, reservas, stock, etc.

·        ***Roles de usuario y control de acceso***

Acceso diferenciado para mozos, cajeros, cocineros, administradores, clientes, con visibilidad y permisos específicos para cada rol.

 

**Limitaciones**

Se detallan aspectos que no serán incluidos en la versión inicial del sistema, pero que podrían contemplarse como mejoras a futuro.

·        ***Integración con medios de pago externos o billeteras virtuales***

El sistema no integrará pasarelas como Mercado Pago, ni sistemas de pago NFC o QR.

 

·        ***Aplicación móvil nativa***

El acceso al sistema será únicamente via navegador web. No habrá apps móviles específicas para Android o iOS.

 

 

·        ***Facturación electrónica integrada***

No se contempla conexión con sistemas oficiales de AFIP u organismos fiscales. La emisión de comprobantes será básica.

·        ***Gestión de recursos humanos y nómina***

No se incluirá control de asistencia, licencias ni módulos de recursos humanos.

·        ***Módulo de proveedores y compras***

La adquisición de insumos se realizará de forma manual. No se incluirá gestión automatizada de órdenes de compra ni ranking de proveedores.

·        ***Inteligencia artificial o aprendizaje automático***

El sistema no realizará predicciones ni análisis basados en modelos de machine learning. Los procesos automatizados estarán basados en reglas y eventos simples.

·        ***Soporte offline***

La aplicación requerirá conexión a internet para funcionar. No tendrá modo offline.

 

## **Especificación de módulos**

### **Funcionales**

·        Registro y gestión de clientes

·        Servicios y gestión de pedidos

·        Gestión de producción / cocina

·        Gestión de reservas y mesas

·        Gestión de stock e ingredientes

·        Gestión de ventas y facturación

·        Armado personalizado de productos (ej: Hamburguesas)

·        Gestión de delivery

·        Generación de informes y estadísticas

### **No funcionales**

·        Seguridad y control de acceso

·        Auditoría

·        Gestión de roles

·        Usabilidad y experiencia de usuario

·        Accesibilidad y navegación responsive

 

## **Descripción de los módulos**

***Registro y gestión de clientes:***

Permite a los clientes crear una cuenta personal con usuario y contraseña para acceder a beneficios, historial de pedidos, promociones y personalización de preferencias. Los usuarios ocasionales podrán ingresar mediante una cuenta genérica. El sistema almacenará datos de contacto, frecuencia de visitas y pedidos anteriores para futuras recomendaciones o campañas de fidelización.

***Servicios y gestión de pedidos (en mesa, mostrador y delivery):***

Facilita la creación de pedidos desde diferentes canales (mesas con pantalla, terminal del mostrador o desde el sistema interno para delivery). Se permite la selección de productos desde la carta digital, la personalización de platos y el seguimiento del estado del pedido. El sistema agrupa pedidos por mesa, cliente y canal de venta.

***Gestión de producción / cocina:***

Centraliza los pedidos confirmados y los organiza para el personal de cocina. Permite dividir las órdenes según sector (comidas, bebidas, postres), marcar tiempos estimados y gestionar el estado de preparación. Incluye notificaciones internas cuando los pedidos están listos para servir o enviar.

***Gestión de reservas y mesas:***

Permite al personal registrar reservas manuales o gestionarlas desde un canal web en el futuro. El sistema visualiza la disponibilidad de mesas en tiempo real, permite asignarlas a clientes al llegar, registrar el tiempo de ocupación y liberar automáticamente una vez cerrada la cuenta. Incluye un mapa de mesas y alertas para reservas próximas.

***Gestión de stock e ingredientes:***

Controla el inventario de materias primas, bebidas, insumos y productos finales. El sistema descuenta automáticamente ingredientes al registrar pedidos, genera alertas cuando el stock llega al mínimo, y permite registrar nuevas entradas por compras o devoluciones. Incluye historial de movimientos.

***Gestión de ventas y facturación:***

Administra el cierre de cuentas, emisión de tickets o facturas, aplicación de descuentos o promociones. Permite vincular los pedidos al cliente registrado para informes personalizados y fidelización.

***Armado personalizado de productos:***

Permite que el cliente modifique productos del menú (agregar, quitar o cambiar ingredientes), reflejando automáticamente el precio actualizado según las modificaciones. Ideal para comidas como hamburguesas, pizzas o sándwiches. Se registra cada variación en el pedido final.

***Gestión de delivery/ventas externas:***

Permite tomar pedidos a domicilio desde el sistema interno o, a futuro, desde un portal web. Incluye datos de dirección, zona de entrega, estado del pedido, asignación a repartidor (manual o automatizada), y control de tiempos de salida y entrega.

***Generación de informes y estadísticas:***

Proporciona paneles e informes parametrizables para el administrador del sistema. Incluye reportes de ventas por fecha, productos más vendidos, rendimiento del personal, turnos con más afluencia, entre otros. Permite exportación en PDF o Excel.

***Seguridad y control de acceso:***

Incluye autenticación de usuarios, cifrado de contraseñas, validación de sesiones y restricción de acceso según permisos asignados a cada rol. Protege la información personal y operativa del sistema.

 

***Auditoría (log de eventos del sistema):***

Registra todas las acciones realizadas por los usuarios dentro del sistema (altas, modificaciones, eliminaciones, accesos), incluyendo fecha, hora y responsable. Útil para trazabilidad, control interno y depuración.

***Gestión de roles y permisos:***

Define distintos niveles de acceso al sistema según el tipo de usuario (administrador, cajero, mozo, cocinero, cliente, etc.). Cada rol tiene funciones y vistas asignadas, asegurando un uso controlado y específico del sistema.

***Usabilidad y experiencia de usuario:***

El sistema será intuitivo, visualmente organizado y accesible desde diferentes dispositivos. Se prioriza la navegación ágil, especialmente en las pantallas de pedido para clientes, y en los módulos de producción para cocina.

***Accesibilidad y navegación responsive:***

Diseñado para adaptarse a pantallas de distintos tamaños (monitores, tablets, pantallas táctiles de mesa), con enfoque en accesibilidad visual y experiencia fluida, sin necesidad de zoom o scroll excesivo.

 

## 

## 

## 

## 

## 

## 

## 

## 

## 

## **Procesos automatizados**

| Denominación | Módulos que intervienen | Descripción del proceso |
| ----- | ----- | ----- |
| Generación dinámica de promociones según stock y clima. | Gestión de stock, Registro y gestión de pedidos, Promociones (submódulos dentro de ventas), Auditoría. |  **Descripción del proceso:** Este proceso funciona como un motor de marketing proactivo. El sistema analiza continuamente variables contextuales como el clima, el día de la semana y el estado del stock. Al detectar una oportunidad (ej: una noche fría con bajo pronóstico de ventas y alto stock de panceta y queso cheddar), en lugar de limitarse a mostrar una promoción pasiva en la carta, el sistema identifica un segmento de clientes afines. Posteriormente, envía notificaciones push personalizadas con una oferta concreta y una llamada a la acción directa para reservar. Ejemplo: "¡Combatí el frío con nuestra burger Doble Bacon Cheddar\! Te invitamos a cenar hoy con papas y gaseosa gratis. ¿Aceptás y te guardamos una mesa para las 21:00?". Si el cliente acepta, el sistema registra la reserva automáticamente, asegurando una venta que de otro modo no se hubiera producido.  **Grado de automatización:** Total  **Disparador:** Doble disparador, programado y por evento. Se ejecuta un análisis principal diariamente a primera hora (ej: 9:00 AM) para planificar el día. Adicionalmente, se activa en tiempo real ante un cambio brusco en las variables monitoreadas (ej: una actualización del pronóstico a "lluvia intensa" a las 19:00, o una baja de ventas inesperada).  **Resultado esperado:**  Ofertas personalizadas enviadas a clientes, con un flujo de conversión que genera reservas de forma autónoma.  **Impacto en registros:** Creación de un registro de reserva asociado al cliente y a la promoción. Registro de la oferta enviada y su estado (aceptada/rechazada) en el historial del cliente. Log de decisión en el módulo de auditoría justificando la campaña (ej: 'Campaña por lluvia y stock de cheddar'). |
| Recomendación inteligente de reservas basado en historial del cliente. |  Registro y gestión de clientes, Registro y gestión de pedidos, armado personalizado de productos, promociones, auditoría. | **Descripción del proceso:** Este sistema transforma la recomendación pasiva en una herramienta de fidelización activa. Basándose en el historial y los datos del cliente, el sistema detecta eventos significativos como un cumpleaños o un aniversario de su primera visita. Ante estos disparadores, el sistema genera y envía una invitación de reserva con una oferta exclusiva. Ejemplo: "¡Feliz cumpleaños, \[Nombre\]\! Festejalo con nosotros y te regalamos una ronda de cerveza artesanal para tu mesa (en reservas de 4 personas o más). ¿Querés reservar ahora?". El flujo permite al cliente aceptar la oferta y gestionar su reserva directamente desde la notificación, aplicando el beneficio de forma automática.  **Grado de automatización:** Total.  **Disparador:**Disparador por evento de datos del cliente. El proceso se activa al detectarse una fecha o condición clave en el perfil del cliente, como el inicio de la semana de su cumpleaños, el aniversario de su primera visita, o al cumplirse un período de inactividad configurable (ej: 90 días sin visitas).  **Resultado esperado:** Campañas de fidelización automáticas que incentivan el regreso de clientes y la planificación de eventos grupales, generando reservas anticipadas.  **Impacto en registros:** Creación de un registro de reserva con el descuento de fidelización aplicado. Registro de la campaña de cumpleaños enviada en el historial del cliente. Log de interacción en el módulo de auditoría (ej: 'Cliente acepta oferta de cumpleaños para 6 personas'). |
|  Sugerencias de recetas alternativas según stock disponible. | Gestión de stock, Gestión de producción, Carta electrónica (Módulo pedidos), Auditoría. | **Descripción del proceso:** Este proceso convierte la gestión de inventario en una oportunidad de venta directa. Cuando el sistema detecta ingredientes con stock elevado o cercanos a su vencimiento (ej: palta o un queso especial como el roquefort), no solo sugiere la creación de un "Especial del día", sino que lo convierte en el centro de una micro-campaña de marketing. Una vez definido el plato especial, el sistema busca en la base de clientes a aquellos con mayor probabilidad de estar interesados y les envía una notificación exclusiva y de tiempo limitado. Ejemplo: "¡Atención fanáticos\! Solo por hoy creamos una burger fuera de carta: la 'Guacamole Blues' con palta fresca y queso azul. ¡Unidades limitadas\! ¿Te gustaría que te reservemos una mesa para venir a probarla esta noche?". La aceptación del cliente genera la reserva de forma inmediata, garantizando la venta del producto y minimizando el desperdicio.  **Grado de automatización:** Parcial a total (según permisos)  **Disparador:** Disparador por umbral de inventario. El proceso de monitoreo automático se activa de forma continua para detectar lotes de ingredientes que crucen los umbrales de riesgo definidos por el usuario. El sistema genera alertas en casos como por ejemplo: Exceso de Stock: Cuando la cantidad de un lote supera el consumo promedio en un porcentaje configurable desde el panel de administración del sistema. Vencimiento Próximo: Cuando la fecha de vencimiento de un lote se encuentra dentro del número de días de antelación establecidos como parámetro en la configuración.  **Resultado esperado:** Reducción del desperdicio de insumos mediante la creación y promoción activa de recetas especiales, generando ventas y reservas que optimizan el uso del inventario.  **Impacto en registros:** Creación de un plato especial temporal en el módulo de menú. Creación de un registro de reserva asociado al plato especial. Registro de la notificación de oportunidad enviada al cliente. Log en auditoría con la razón del plato especial (ej: 'Plato anti-desperdicio por exceso de stock de palta'). Impacto en stock proyectado. |

 

 

## **Estimación de tamaño por módulo**

 

| Módulo | Porcentaje de participación / Producto |
| ----- | ----- |
| Gestión de reservas | 15% |
| Carta electrónica / pedidos | 20% |
| Gestión de cocina | 15% |
| Gestión de mesas | 10% |
| Caja y sistema de cobro | 10% |
| Gestión de stock | 10% |
| Gestión de usuario y roles | 5% |
| Auditoría | 5% |
| Notificaciones | 5% |
| Estadísticas e informes | 5% |
| **Total** | 100% |

 

## **Entorno tecnológico y metodológico**

| Lenguajes de programación: | PHP |
| :---- | :---- |
| **Framework:** | Laravel 11 |
| **Arquitectura:** | MVC |
| **Motor de Base de Datos:** | PostgreSQL |
| **Metodología seleccionada:** | Proceso Unificado (UP) |
| **Tipo de proyecto:** | Con cliente final |

**Planificación de actividades**

| Actividad | Fecha de inicio | Fecha de finalización |
| ----- | ----- | ----- |
| Redacción de objetivos y alcance del sistema | 17/05/2025 | 21/05/2025 |
| Identificación de actores y casos de uso generales | 22/05/2025 | 24/05/2025 |
| Diagrama de casos de uso | 25/05/2025 | 26/05/2025 |
| Casos de uso de alto nivel y extendido | 27/05/2025 | 30/05/2025 |
| Estructura funcional de módulos | 31/05/2025 | 05/06/2025 |
| Requisitos funcionales y no funcionales | 06/06/2025 | 10/06/2025 |
| Modelo de dominio | 11/06/2025 | 14/06/2025 |
| Diagrama de clases | 15/06/2025 | 19/06/2025 |
| Modelo de datos y normalización | 20/06/2025 | 25/06/2025 |
| Diagramas de secuencia | 26/06/2025 | 30/06/2025 |
| Contratos y reglas de negocio | 01/07/2025 | 05/07/2025 |
| Diseño de pantallas e interfaces | 06/07/2025 | 12/07/2025 |
| Desarrollo de módulos: Producción y Stock | 21/07/2025 | 11/08/2025 |
| Desarrollo de módulo Proveedores | 12/08/2025 | 22/08/2025 |
| Desarrollo de módulos: Reportes, Auditoría, Predictivo | 23/08/2025 | 15/09/2025 |
| Pruebas unitarias e integración | 16/09/2025 | 30/09/2025 |
| Pruebas de aceptación con el cliente | 01/10/2025 | 10/10/2025 |
| Migración de datos y configuración del entorno productivo | 11/10/2025 | 21/10/2025 |
| Elaboración de manuales de usuario | 22/10/2025 | 28/10/2025 |
| Entrenamiento a usuarios y administradores | 29/10/2025 | 07/11/2025 |
| Despliegue y monitoreo inicial | 07/11/2025 | 14/11/2025 |

 

 

## **Estado de avance \#1**

 

 

| Fecha de presentación |  |
| :---- | :---- |
| **Fecha de inicio efectivo del proyecto** |  |
| **Estado de avance** |  |

 

*Descripción de avances logrados*

 

| Estado de avance en el desarrollo del producto |    |
| :---- | :---- |
| **Problemas detectados que han podido retrasar el desarrollo del proyecto** |  |
| **Avances previstos para las próximas 4 semanas** |  |

 

 

 

| Observaciones del equipo de cátedra |  |
| :---- | :---: |

 

*Firma por el equipo de cátedra:*

*Fecha:*

## **Estado de avance \#2**

 

 

| Fecha de presentación |  |
| :---- | :---- |
| **Fecha de inicio efectivo del proyecto** |  |
| **Estado de avance** |  |

 

*Descripción de avances logrados*

 

| Estado de avance en el desarrollo del producto |  |
| :---- | :---- |
| **Problemas detectados que han podido retrasar el desarrollo del proyecto** |  |
| **Avances previstos para las próximas 4 semanas** |  |

 

 

 

| Observaciones del equipo de cátedra |  |
| :---- | :---: |

 

*Firma por el equipo de cátedra:*

*Fecha:*

 

## **Estado de avance \#3**

 

 

| Fecha de presentación |  |
| :---- | :---- |
| **Fecha de inicio efectivo del proyecto** |  |
| **Estado de avance** |  |

 

*Descripción de avances logrados*

 

| Estado de avance en el desarrollo del producto |  |
| :---- | :---- |
| **Problemas detectados que han podido retrasar el desarrollo del proyecto** |  |
| **Avances previstos para las próximas 4 semanas** |  |

 

 

 

| Observaciones del equipo de cátedra |    |
| :---- | :---- |

 

*Firma por el equipo de cátedra:*

*Fecha:*

 

## **Registro de cambios en el proyecto**


 

| Fecha de presentación |  |
| :---- | :---- |
| **Módulo afectado** |  |
| **Motivo** |  |

 

*Descripción detallada*

 

| Estado previo |    |
| :---- | :---- |
| **Estado posterior (aplicando el cambio)** |    |
| **Justificación** |   |

 

 

 

| Observaciones del equipo de cátedra |    |
| :---- | :---- |

 

*Firma por el equipo de cátedra:*

*Fecha:*

 

 

 

---


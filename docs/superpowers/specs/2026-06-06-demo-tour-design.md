# Modo demo guiado para usuarios

## Objetivo

Agregar un modo demo entendido como un recorrido guiado dentro de la aplicacion, sin sembrar datos ni crear cuentas ficticias. El objetivo es ayudar a usuarios nuevos a entender que hace cada seccion, pestana y boton importante antes de participar.

## Alcance

El alcance cubre:

- un banner inicial de bienvenida integrado al diseno visual actual
- la decision del usuario entre iniciar un tour o entrar directo a participar
- un tour contextual por las vistas principales de la aplicacion
- explicaciones cortas sobre secciones, pestanas, botones y bloques clave
- un mecanismo visible para reabrir el tour despues
- persistencia local del estado del tour para no interrumpir repetidamente
- comportamiento adaptado a movil y desktop

El alcance no cubre:

- siembra de datos demo
- creacion de usuarios ficticios
- cambios funcionales de negocio en registro, facturas, ranking o premios
- traducciones adicionales o soporte multi idioma

## Resultado esperado

Cuando un usuario entre por primera vez, vera un banner de bienvenida coherente con la interfaz actual. Desde ahi podra elegir entre hacer un tour o entrar de inmediato. Si hace el tour, la aplicacion lo guiara por las secciones principales con ayudas visuales y texto breve. Si decide omitirlo o terminarlo, seguira teniendo una forma clara de volver a abrirlo cuando quiera.

## Experiencia de usuario

### 1. Banner inicial

El banner aparecera al entrar cuando el usuario aun no haya descartado o completado el onboarding.

Contenido esperado:

- titulo de bienvenida
- una explicacion breve de que la plataforma puede mostrarse en un recorrido rapido
- boton primario `Quiero hacer un tour`
- boton secundario `Prefiero participar`

Copy base propuesto:

- titulo: `Bienvenido a la experiencia`
- texto: `Podemos darte un recorrido rapido para mostrarte como funciona cada seccion antes de participar.`
- CTA primario: `Quiero hacer un tour`
- CTA secundario: `Prefiero participar`

El banner debe seguir el lenguaje visual existente de la app, especialmente el tratamiento azul profundo, brillos suaves, contornos luminosos y acentos dorados ya presentes en la experiencia actual. No debe verse como un modal generico ni como una capa externa al sistema.

### 2. Inicio del tour

Al pulsar `Quiero hacer un tour`, el banner desaparece y comienza un recorrido guiado contextual. El tour usara tarjetas cortas con texto claro y navegacion simple.

Controles del tour:

- `Siguiente`
- `Anterior`
- `Salir`

El usuario siempre debe poder abandonar el tour sin bloquear el uso normal de la aplicacion.

### 3. Reapertura del tour

Despues de omitirlo o completarlo, la aplicacion dejara un acceso visible pero discreto para reabrir el recorrido.

Ubicacion recomendada:

- un boton `Ver tour` en el header si el espacio lo permite
- como alternativa o refuerzo, un acceso dentro de `Mi Cuenta`

La implementacion debe priorizar una ubicacion que ya exista visualmente en la app para no introducir ruido innecesario.

## Flujo del tour

### Paso 1. Bienvenida

Explica que el usuario vera un recorrido corto para aprender como moverse dentro de la plataforma.

Debe responder:

- que es el tour
- cuanto esfuerzo implica
- que puede cerrarse en cualquier momento

### Paso 2. Navegacion principal

Explica para que sirve cada pestana principal del sistema.

Elementos a resaltar:

- `La Cancha`
- `Entrenamiento`
- `Ranking`
- `Vitrina`
- `Mi Cuenta`

### Paso 3. La Cancha

Explica que esa vista sirve para ver partidos, fases disponibles y realizar pronosticos.

Elementos a resaltar:

- selector de ronda o fase
- tarjetas de partidos
- controles de marcador
- boton para enviar la prediccion

### Paso 4. Entrenamiento

Explica que ahi se registran o escanean facturas para participar.

Elementos a resaltar:

- selector entre escaneo y registro manual
- campos principales de captura
- boton de registro o envio

### Paso 5. Ranking

Explica que esa vista permite revisar posicion, avance o comparacion visible con otros participantes.

Elementos a resaltar:

- bloques de progreso
- tablas, tarjetas o indicadores que ya existan en la pantalla

### Paso 6. Vitrina

Explica que ahi se consultan premios, beneficios o elementos asociados a canjes y metas.

Elementos a resaltar:

- cards o bloques de premios
- estado visible de premios o acciones disponibles

### Paso 7. Mi Cuenta

Explica donde revisar y actualizar la informacion personal, avatar, terminos o datos relacionados con la cuenta.

Elementos a resaltar:

- accesos de perfil
- datos personales
- acciones relacionadas con terminos o configuracion

### Paso 8. Cierre

Mensaje final breve indicando que el usuario ya conoce la plataforma y puede comenzar a participar.

Accion principal recomendada:

- `Empezar ahora`

## Comportamiento visual

### Banner

El banner debe usar:

- fondo coherente con las superficies actuales
- bordes y halos alineados al sistema azul actual
- acentos dorados para el CTA primario o detalles de enfasis
- tipografia ya presente en la experiencia

Debe convivir con el layout actual sin romper el header ni las tarjetas ya existentes.

### Tarjetas del tour

Las tarjetas explicativas deben ser breves y escaneables. No deben incluir parrafos largos.

Cada tarjeta debe:

- tener un titulo claro
- explicar un solo concepto principal
- indicar que hacer a continuacion si aplica

### Resaltado de elementos

El tour debe dirigir la atencion usando una combinacion de:

- halo o borde destacado sobre el elemento objetivo
- atenuacion leve del resto del contenido
- posicionamiento de la tarjeta cerca del elemento cuando sea posible

La atenuacion no debe impedir leer ni usar la interfaz.

## Persistencia

El estado del onboarding se guardara localmente en el navegador para evitar que el banner aparezca en cada entrada.

Estados minimos:

- `not_started`
- `dismissed`
- `in_progress`
- `completed`

Comportamiento esperado:

- si el usuario nunca lo ha visto, se muestra el banner
- si pulsa `Prefiero participar`, se marca como descartado y no vuelve a mostrarse automaticamente
- si inicia el tour, se marca como en progreso
- si termina el tour, se marca como completado
- en cualquier estado distinto de `not_started`, el usuario puede reabrir el tour manualmente

No se requiere persistencia en backend para esta primera version.

## Responsive

### Movil

La tarjeta del tour debe ubicarse arriba o abajo del contenido, ocupando poco espacio y evitando tapar botones criticos. La navegacion debe ser facil con el pulgar.

### Desktop

La tarjeta puede flotar cerca del elemento resaltado, aprovechando mejor el espacio horizontal sin interferir con la lectura del contenido.

## Integracion tecnica esperada

La solucion debe reutilizar la estructura actual del frontend en lugar de introducir una libreria pesada si no es necesaria. La prioridad es una integracion limpia con:

- la navegacion ya existente entre vistas
- el estado actual de la app cliente
- las clases y tokens visuales ya presentes en la hoja de estilos

La implementacion puede resolverse con un estado global ligero en `App.tsx` o con un wrapper pequeno para el recorrido, siempre que:

- no complique la logica de negocio actual
- permita mapear pasos del tour a vistas reales
- facilite agregar o ajustar copy despues

## Riesgos y decisiones

### Riesgo 1. Elementos cambiantes por vista

Algunos botones o bloques pueden variar segun datos del usuario o el estado de la aplicacion.

Decision:

El tour debe apuntar primero a elementos estructurales estables. Donde un elemento pueda no existir en ciertos estados, el paso debe poder degradar con una explicacion general sin romper el recorrido.

### Riesgo 2. Saturacion visual en movil

Mucho texto o una superposicion agresiva puede volver incomoda la experiencia.

Decision:

Priorizar textos cortos, una sola idea por paso y una tarjeta compacta.

### Riesgo 3. Apariencia ajena al sistema

Un banner generico o demasiado distinto romperia la percepcion de calidad del producto.

Decision:

Construir banner y tour usando el mismo lenguaje visual del sistema actual, sin estilos prestados que se sientan externos.

## Criterios de aceptacion

1. El usuario ve un banner inicial solo cuando aun no ha descartado o completado el onboarding.
2. El banner ofrece exactamente dos opciones principales: `Quiero hacer un tour` y `Prefiero participar`.
3. El tour explica las secciones principales de la app y sus acciones clave.
4. El usuario puede navegar entre pasos con controles simples y salir en cualquier momento.
5. La interfaz ofrece una forma visible de reabrir el tour despues.
6. El estado del banner y del tour se conserva localmente en el navegador.
7. El banner y las tarjetas del tour respetan el lenguaje visual actual de la app.
8. La experiencia funciona tanto en movil como en desktop.

## Fuera de alcance inmediato

Estas mejoras pueden evaluarse despues, pero no forman parte de esta entrega:

- retomar el tour desde el ultimo paso en vez de reiniciarlo
- personalizar el tour por tipo de usuario
- guardar el estado del tour en backend
- analitica detallada de pasos vistos o abandonados

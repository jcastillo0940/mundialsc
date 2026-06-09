# Demo Tour Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar un banner de bienvenida y un tour guiado reabrible dentro del shell del cliente sin alterar la logica de negocio existente.

**Architecture:** Se agregara una capa liviana de onboarding en el frontend cliente compuesta por un estado persistido en `localStorage`, una configuracion declarativa de pasos y un componente visual reutilizable para banner y tarjeta flotante del tour. La navegacion del tour usara las vistas reales ya existentes (`cancha`, `facturas`, `perfil`, `reglas`, `cuenta`) y resaltara objetivos mediante atributos de datos y clases CSS.

**Tech Stack:** React 19, TypeScript, React Router, CSS existente de la app, Vite build como verificacion.

---

### Task 1: Definir modelo del tour y helpers de persistencia

**Files:**
- Create: `frontend/src/demoTour.ts`
- Modify: `frontend/src/App.tsx`

- [ ] Crear tipos del tour, lista de pasos y helpers de persistencia local.
- [ ] Integrar el estado base en `App.tsx` para abrir, avanzar, cerrar y reabrir el tour.

### Task 2: Renderizar banner y tarjeta del tour

**Files:**
- Create: `frontend/src/components/ClientDemoTour.tsx`
- Modify: `frontend/src/App.tsx`

- [ ] Implementar componente visual para banner inicial y tarjeta flotante del tour.
- [ ] Conectar callbacks del tour con la navegacion actual sin duplicar rutas ni estados del cliente.

### Task 3: Añadir puntos de anclaje y resaltado contextual

**Files:**
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/components/CuentaView.tsx`
- Modify: `frontend/src/style.css`

- [ ] Agregar atributos `data-demo-target` en header, navegacion, botones y contenedores clave.
- [ ] Aplicar estilos para halo, overlay, banner, tarjeta y boton `Ver tour`.
- [ ] Ajustar comportamiento responsive para movil y desktop.

### Task 4: Verificar integracion

**Files:**
- Modify: `frontend/src/demoTour.ts`
- Modify: `frontend/src/components/ClientDemoTour.tsx`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/style.css`

- [ ] Revisar que el recorrido cubra Bienvenida, Navegacion, La Cancha, Entrenamiento, Ranking, Vitrina, Mi Cuenta y Cierre.
- [ ] Ejecutar `npm run build` en `frontend` y corregir cualquier error de tipos o bundling.
- [ ] Dejar notas claras sobre limitaciones de prueba si no existe infraestructura automatizada de UI.

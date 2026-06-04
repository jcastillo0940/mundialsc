# Exact Auth Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rehacer la vista publica de autenticacion para que coincida visualmente con la referencia del usuario sin romper el flujo actual de login y registro.

**Architecture:** Se conserva la logica existente dentro de `frontend/src/App.tsx`, pero se sustituye la composicion visual del bloque auth y se redefine la capa CSS final del auth para responder al nuevo layout. Los assets faltantes se reemplazan por placeholders SVG con rutas estables.

**Tech Stack:** React 19, TypeScript, Vite, CSS global existente en `frontend/src/style.css`

---

### Task 1: Documentacion base

**Files:**
- Create: `docs/superpowers/specs/2026-06-04-auth-redesign-design.md`
- Create: `docs/superpowers/plans/2026-06-04-auth-redesign-exact.md`

- [ ] Step 1: Guardar spec aprobada
- [ ] Step 2: Guardar plan de implementacion

### Task 2: Assets placeholder

**Files:**
- Create: `frontend/public/redesign/auth-logo-super-carnes.svg`
- Create: `frontend/public/redesign/auth-player-left.svg`
- Create: `frontend/public/redesign/auth-mascot-center.svg`
- Create: `frontend/public/redesign/auth-ball-center.svg`
- Create: `frontend/public/redesign/auth-stadium-bg.svg`
- Create: `frontend/public/redesign/auth-crowd-overlay.svg`
- Create: `frontend/public/redesign/auth-confetti-layer.svg`

- [ ] Step 1: Crear placeholders SVG con tamaño y rotulo util
- [ ] Step 2: Validar rutas desde el navegador

### Task 3: Auth layout

**Files:**
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/style.css`

- [ ] Step 1: Reemplazar el hero y la barra inferior por una composicion fiel al arte
- [ ] Step 2: Revestir el panel auth actual con el nuevo card visual
- [ ] Step 3: Mantener intactos los handlers existentes

### Task 4: Verificacion

**Files:**
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/style.css`

- [ ] Step 1: Ejecutar `npm.cmd run build`
- [ ] Step 2: Levantar o reutilizar la app en navegador
- [ ] Step 3: Comparar visualmente contra la referencia y ajustar

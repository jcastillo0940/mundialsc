# Panama Phone Prefix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mostrar `+507` fijo en el registro y permitir solo 8 digitos locales en el input.

**Architecture:** El estado sigue almacenando el telefono completo para no tocar la interfaz con backend. La UI separa el prefijo fijo del input y deriva los digitos locales desde el valor persistido.

**Tech Stack:** React, TypeScript, CSS

---

### Task 1: Ajustar logica del telefono

**Files:**
- Modify: `frontend/src/App.tsx`

- [ ] **Step 1: Derivar los digitos locales desde el valor almacenado**
- [ ] **Step 2: Mantener la normalizacion al formato backend**
- [ ] **Step 3: Actualizar mensajes de validacion**

### Task 2: Ajustar UI y estilos

**Files:**
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/style.css`

- [ ] **Step 1: Reemplazar el input simple por un contenedor compuesto**
- [ ] **Step 2: Agregar estilos especificos**

### Task 3: Verificacion

**Files:**
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/style.css`

- [ ] **Step 1: Ejecutar build del frontend**

Run: `npm.cmd run build`
Expected: compilacion exitosa sin errores de TypeScript o Vite.

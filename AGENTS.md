# Reglas de Proyecto - MOODI

Este archivo versiona las reglas de trabajo del equipo para que toda persona que trabaje en este repo siga el mismo standard.

## 1) Filosofia de trabajo

- Conceptos > codigo: primero entender el problema, despues implementar.
- Sin shortcuts: no se aprueban cambios "rapidos" sin criterio tecnico.
- AI es una herramienta: la decision final y la responsabilidad son humanas.

## 2) Flujo Git (obligatorio)

- Nunca trabajar en `main`.
- Crear siempre una branch de feature/fix/chore antes de tocar codigo.
- Abrir PR para integrar cambios; evitar cambios directos en la rama principal.

## 3) Testing Strategy B (obligatoria por issue)

Cada issue debe incluir, como minimo, estos 3 tipos de prueba:

1. Happy path del caso principal.
2. Seguridad/autorizacion (acceso permitido/denegado segun rol/contexto).
3. Edge case relevante del flujo (dato limite, estado invalido o condicion no feliz).

Sin estos 3 puntos, el issue no cumple DoD.

## 4) Validacion tecnica

- No asumir: toda afirmacion tecnica se verifica en codigo, docs o evidencia ejecutable.
- Si hay duda, investigar primero y recien despues afirmar.
- Si una hipotesis es incorrecta, documentar por que y corregir con evidencia.

## 5) Build y comandos

- No correr build al finalizar cambios como paso por defecto.
- Ejecutar solo los comandos necesarios para validar el objetivo del issue (tests/checks puntuales).

## 6) Convenciones multi-tenant (stancl/tenancy)

- Central DB: `tenants`, `domains`, metadatos globales y configuracion cross-tenant.
- Tenant DB: tablas operativas del negocio (usuarios del negocio, inventario, ventas, caja, cuentas corrientes, compras, devoluciones, auditoria, tareas, finanzas operativas).
- Separar claramente migraciones centrales vs migraciones tenant; no mezclar responsabilidades.

## 7) Seguridad

- Nunca commitear secretos (`.env`, tokens, credenciales, keys, dumps sensibles).
- Usar variables de entorno y placeholders para configuraciones sensibles.

## 8) Commit messages

- Usar Conventional Commits.
- Formato: `type(scope): mensaje` (ej.: `feat(auth): agrega validacion de permisos`).
- Prohibido agregar atribuciones de IA (por ejemplo, `Co-Authored-By`).

## 9) Definition of Done (DoD) por issue

Un issue se considera terminado solo si:

- Cumple alcance funcional acordado.
- Incluye Testing Strategy B completa (3 pruebas minimas).
- Respeta convenciones de arquitectura y tenancy del proyecto.
- No introduce secretos ni deuda tecnica critica conocida.
- Queda listo para revision por PR con contexto suficiente.

## 10) Checklist de PR (obligatoria antes de abrir)

Antes de abrir una Pull Request, validar este checklist:

- [ ] El trabajo esta en una branch de feature/fix/chore (no `main`).
- [ ] Se implemento Testing Strategy B completa (happy path + seguridad/autorizacion + edge case).
- [ ] Se verificaron permisos/roles en rutas y acciones sensibles.
- [ ] No se incluyen secretos ni archivos sensibles (`.env`, tokens, credenciales, dumps).
- [ ] El PR incluye contexto suficiente: objetivo, alcance, pruebas ejecutadas y decisiones relevantes.

## 11) Registro de hallazgos y pendientes (vault Obsidian)

Cuando durante el trabajo aparezca un problema/bug o una tarea pendiente de verificación, registrarlo en el vault hermano de conocimiento (fuera del repo):

- Vault: `/home/johann/Escritorio/Trabajo Final/MOODI-Vault`
- Fallo o cosa rota → nueva nota en `Hallazgos/` con nombre `YYYY-MM-DD - {título corto}.md` usando la plantilla `Hallazgos/_plantilla-hallazgo.md` (síntoma, evidencia, pasos para reproducir, causa raíz, fix propuesto). Cerrar/actualizar `estado` cuando se resuelva.
- Verificación manual del sistema → checklist por módulo en `Validaciones/` (copiar `00 - Checklist maestro.md`), marcando `[x]` / `[ ]`, y reflejar el avance en `00 - Mapa de módulos.md`.
- Al cerrar un barrido → crear un plan en `Planes/` y enlazarlo en `00 - Índice de planes.md`.
- Regla de oro: no arreglar en el momento y olvidar — anotar el hallazgo primero; el fix se planifica después.
- Si el `00 - Inicio.md` del vault tiene una sección "🔄 Coordinación" con roles de chat, seguir ese protocolo por sobre esta regla (ej.: no editar Mapa/Inicio si los consolida otro chat).

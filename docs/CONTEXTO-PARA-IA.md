# FAMEP — Contexto del proyecto para IA

> **Generado:** 3 de noviembre de 2026
> **Propósito:** documento de traspaso para que otra IA (o un desarrollador nuevo) entienda qué es FAMEP, cómo está construido, qué se ha agregado/modificado y qué reglas no debe romper.
> **Fuente:** código del repositorio + `docs/COMPOSICION-APP.md` + reportes de WhatsApp.
> **Nota:** al generar este documento Docker estaba apagado; los datos de BD citados son el último estado verificado (28-oct-2026).

---

## 1. Qué es FAMEP

Aplicación web **monolítica Laravel** para gestionar producción y calidad de estructuras metálicas:

- Obras → piezas/marcas → fases (Armado, Soldadura, Pintura).
- Dictámenes de calidad con evidencia fotográfica.
- Portal público de consulta para clientes (sin login).
- Reportes operativos y auditoría.
- Alertas por WhatsApp cuando Calidad rechaza o manda a reproceso.

| Aspecto | Definición |
|---|---|
| Framework | Laravel **13.30.1** |
| Lenguaje | PHP **8.3** |
| Base de datos | PostgreSQL **18** |
| Servidor | Nginx + PHP-FPM (Docker) |
| Frontend | Blade + Tailwind + Alpine.js + Chart.js **locales** (sin CDN, sin Vite en runtime) |
| Automatización | n8n + Evolution API **v1.8.2** (WhatsApp) |
| Pruebas | Harness propio en `tests/harness/` (13 suites, **453 checks**) |

---

## 2. Estado actual (snapshot)

- **Funcional y verificado:** 13 suites, **453 OK, 0 FAIL** (última corrida: 28-oct-2026).
- **Docker Compose:** servicios `app`, `web`, `db`, `n8n`, `evolution-api` (+ `adminer` en dev).
- **Sin control de versiones:** el directorio **no es un repo git**. Los cambios no están versionados.
- **Esquema sin migraciones:** la fuente de verdad es `docker/postgres/init/01-inicial.sql`.
- **Cambios recientes pendientes de desplegar a producción:** el código vive dentro de la imagen; hay que reconstruirla (ver §11).

---

## 3. Arquitectura

```
Navegador
   │
   ▼
Nginx (web:9090) ──► PHP-FPM (app)
   │
   ▼
routes/web.php
   ├─ guest            → login
   ├─ público          → /consulta (portal por código FAMEP-XXXX)
   ├─ auth             → perfil / logout
   └─ auth + jefatura  → panel administrativo
   │
   ▼
12 controladores ──► 13 modelos Eloquent
   │                  ├─ AuditoriaService (observers, escrituras)
   │                  └─ SendWhatsAppAlertJob (dispatchSync → n8n → Evolution)
   ▼
PostgreSQL 18
```

### Capas

| Capa | Componentes |
|---|---|
| Rutas | `routes/web.php` |
| Middleware | `EnsureJefatura` (expulsa clientes del panel) |
| Controladores | 12 clases |
| Modelos | 13 clases |
| Servicios | `AuditoriaService` |
| Jobs | `SendWhatsAppAlertJob` |
| Providers | `AppServiceProvider` (registra observers) |
| Vistas | 40 Blade |

---

## 4. Modelo de datos

**14 tablas de dominio:** `obras`, `piezas`, `fases_pieza`, `observaciones_calidad`, `evidencias_fotograficas`, `operarios`, `operario_fase`, `usuarios`, `logs_auditoria`, `cat_fases`, `cat_estatus_fase`, `cat_estatus_pieza`, `cat_tipos_estructura`, `cat_roles`.

**6 tablas de infraestructura Laravel:** `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs`.

### Estados de fase (`cat_estatus_fase`)

| ID | Nombre | Significado |
|---|---|---|
| 1 | En Proceso | Producción trabajando; admite dictamen |
| 2 | En Reproceso | Calidad pidió corrección; admite dictamen |
| 3 | Rechazada | Marca rechazada; **no** admite nuevos dictámenes |
| 4 | Aprobada por Calidad | Espera cierre de Producción; no admite dictámenes |
| 5 | Completada | Cerrada por Producción; dictámenes **inmutables** |

### Reglas de dictamen

- Un dictamen `Aprobado` **no cierra** la fase: la deja en `Aprobada por Calidad` hasta que Producción la completa.
- `Rechazado` → fase `Rechazada`; `Enviado a Reproceso` → fase `En Reproceso`.
- El estado de la fase se recalcula desde el **último dictamen por `id`** (no por `editado_at`).
- En fase `Completada` los dictámenes son de solo lectura (no editar ni eliminar).
- Evidencias: `evidencias_fotograficas.observacion_calidad_id` con `ON DELETE CASCADE`; los archivos físicos se borran explícitamente desde el controlador.

---

## 5. Roles y permisos

| ID | Rol | Acceso |
|---|---|---|
| 1 | Jefe de Producción | Panel completo; **no** dictamina ni edita/elimina dictámenes; único que ve el reporte de auditoría |
| 2 | Jefe de Calidad | Panel + dictámenes + evidencias; no gestiona operarios |
| 3 | Visitante / Cliente | Solo portal público `/consulta` |

- Autorización en controladores: `esProduccion()`, `esCalidad()`, `esCliente()`.
- Las rutas del panel usan `auth` + `jefatura`.
- Cuentas reales (sin contraseñas): `produccion@famep.com` (rol 1), `calidad@famep.com` (rol 2).

---

## 6. Decisiones y convenciones que NO se deben romper

1. **Docker con perfiles dev/prod.** Dev monta el código por bind-mount; prod lo hornea en la imagen.
2. **`.env.docker` manda.** `env_file: .env.docker` tiene prioridad sobre `.env`.
3. **El dump es la verdad.** No crear migraciones de dominio; el esquema se edita en `docker/postgres/init/01-inicial.sql`.
4. **Sin Vite en runtime.** Tailwind/Alpine/Chart.js viven en `public/vendor/`; no usar CDN.
5. **Evidencias por Nginx.** `/storage/` es alias de `storage/app/public/`.
6. **Zona horaria Opción A:**
   - Almacenamiento y cálculos en **UTC**.
   - Presentación con `APP_DISPLAY_TIMEZONE=America/Mexico_City` vía `App\Support\FechaLocal`.
   - Filtros por día: `inicioDiaUtc()` / `finDiaUtc()`.
   - No reescribir fechas históricas.
7. **Auditoría:** solo escrituras, con valores previos/nuevos, **nunca** `password_hash`. El reporte de auditoría es solo para Producción.
8. **Archivos basura de la raíz:** `email.'`, `nombre.PHP_EOL))`, `CLAUDE.md` **no forman parte del runtime y no se deben borrar** sin autorización.
9. **QR de WhatsApp:** no se guarda como archivo; es temporal y se pide a Evolution. `qr.html` fue eliminado por estar expirado.
10. **Idioma:** UI, mensajes, comentarios y documentación en **español**.

---

## 7. Funcionalidad implementada

| Módulo | Estado |
|---|---|
| Autenticación (login/logout, bcrypt 12) | Completo |
| Portal público por código `FAMEP-XXXX` | Completo |
| Dashboard con KPIs y notificaciones | Completo |
| Obras: CRUD + detalle | Completo |
| Piezas por obra: CRUD | Completo |
| Fases por pieza: alta, operario, fechas, estatus | Completo |
| Dictámenes: alta, edición, eliminación, historial, inmutabilidad | Completo |
| Evidencias fotográficas (fase y dictamen) | Completo |
| Operarios: CRUD, habilitación multifase, especialidad, bloqueo por incompatibilidad | Completo |
| Reportes: avance, calidad, productividad, hallazgos, historial, auditoría | Completo (filtros + CSV + gráficas) |
| Perfil: cambio de contraseña | Completo |
| Auditoría automática por observers | Completo |
| Alertas WhatsApp (RF-33) | Completo y corregido |

---

## 8. Historial de cambios (lo agregado/modificado)

### 8.1 Correcciones de infraestructura y UI (previas al 28-oct-2026)

| Cambio | Detalle |
|---|---|
| Puertos | Se movieron a **9090** (web), **9091** (Evolution), **9092** (Adminer), **5433** (Postgres), **5678** (n8n). Los 8080–8082 quedaron bloqueados por rangos dinámicos de Windows (Hyper-V/WinNAT). |
| Login sin CDN | Todas las vistas usan assets locales de `public/vendor`. |
| Healthcheck de producción | Ahora verifica PHP-FPM en `127.0.0.1:9000` (antes apuntaba a un HTTP inexistente). |
| Suite `estatus_obra` | Se hizo data-independiente: toma línea base de KPIs y valida el delta, sin depender de cuántas obras reales existan. |

### 8.2 Zona horaria (28-oct-2026)

- Se adoptó **Opción A**: BD y PHP en UTC; presentación en `America/Mexico_City`.
- Nueva clase `app/Support/FechaLocal.php` con `zona()`, `formato()`, `ahora()`, `inicioDiaUtc()`, `finDiaUtc()`, `hoyUtc()`.
- Se aplicó en: dictámenes, reportes, CSV, filtros por día, portal, notificaciones, semáforo de obra y payload de WhatsApp.
- Se agregó la suite `zona_horaria` (8 checks).
- n8n y Evolution usan `TZ=America/Mexico_City` solo para logs/UI.

### 8.3 WhatsApp: diagnóstico y documentación (28-oct-2026)

- Se verificó la cadena Laravel → n8n → Evolution y la instancia `famep-wa` (`status: open`).
- Se creó `docs/REPORTE-QR-WHATSAPP.md`: el cambio de puerto **no** invalidó el QR ni la vinculación; la sesión persiste en el volumen `evolution_instances`.
- Se eliminó `qr.html` (QR expirado del 9-oct) y se documentó la vinculación en `COMPOSICION-APP.md`.
- Se diagnosticó la ejecución n8n 132: usó `telefono_destino: "0000000000"` (prueba), por eso cayó en la rama "Teléfono omitido". No fue un fallo real.

### 8.4 Corrección del flujo de alertas WhatsApp (28-oct-2026)

**Bug reportado:** al eliminar un dictamen sí llegaba WhatsApp; al dictaminar no llegaba nada.

**Causa raíz encontrada:**

1. `destroy()` reenviaba una alerta con el **último dictamen que quedaba**, no con el eliminado. Producía una "alerta fantasma": un rechazo histórico con la fecha de hoy y el inspector que borra.
2. El dictamen nuevo era `Aprobado`, que por RF-33 no notifica. La UI no lo advertía.

**Cambios aplicados:**

| Archivo | Cambio |
|---|---|
| `app/Http/Controllers/ObservacionCalidadController.php` | `destroy()` ya **no** envía alerta (solo auditoría). `update()` notifica **solo si cambia el resultado**. |
| `app/Jobs/SendWhatsAppAlertJob.php` | Timeout 5 s → **15 s configurable**; ahora revisa `$response->failed()` y registra `status` + `body`. |
| `config/services.php` | Nueva clave `n8n.timeout`. |
| `.env.docker` | Nueva variable `N8N_WEBHOOK_TIMEOUT=15`. |
| `resources/views/piezas/show.blade.php` | Aviso: solo Rechazar/Reproceso generan alerta; Aprobar no. |
| `tests/harness/whatsapp_flujo.php` | Suite nueva (23 checks). |
| `tests/harness/bootstrap_test.php` | `Http::fake()` global: las pruebas ya no disparan WhatsApp real. |
| `docs/COMPOSICION-APP.md` | RF-33 actualizado; 13 suites / 453 checks. |
| `docs/REPORTE-ALERTAS-WHATSAPP-FLUJO.md` | Reporte completo del diagnóstico y la solución. |

**Comportamiento final de alertas:**

| Acción | ¿WhatsApp? |
|---|---|
| Dictaminar Rechazado / Reproceso | Sí |
| Dictaminar Aprobado | No |
| Editar solo descripción o fotos | No |
| Cambiar Aprobado → Reproceso | Sí |
| Eliminar dictamen | No |

### 8.5 Documentación (3-nov-2026)

- Se agregó este documento de contexto para IA.

---

## 9. Pruebas

### Cómo ejecutar

```bash
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/<suite>.php
```

Cada suite corre contra la BD real dentro de una **transacción con rollback**: no deja datos y no debe enviar WhatsApp real (`Http::fake()` global en el bootstrap).

### Suites

| Suite | Checks |
|---|---|
| `flujo` | 15 |
| `estatus_obra` | 23 |
| `vistas` | 25 |
| `portal` | 36 |
| `obras_gestion` | 27 |
| `piezas_gestion` | 30 |
| `dictamenes_gestion` | 74 |
| `perfil_gestion` | 25 |
| `reportes_gestion` | 80 |
| `operarios_gestion` | 47 |
| `auditoria_gestion` | 40 |
| `zona_horaria` | 8 |
| `whatsapp_flujo` | 23 |
| **Total** | **453 OK, 0 FAIL** |

---

## 10. Integración WhatsApp (estado funcional)

```
Dictamen (Rechazado / Reproceso, o cambio de resultado)
   │
   ▼
SendWhatsAppAlertJob (dispatchSync, timeout 15 s)
   │  POST JSON
   ▼
n8n: webhook /famep-alerta-calidad
   │  IF ^521[0-9]{10}$
   ├─ true  → HTTP Request → Evolution /message/sendText/famep-wa
   └─ false → "Telefono omitido"
   ▼
WhatsApp
```

- Workflow n8n `My workflow` (id `JA5Q691rsDpfzQs3`), **activo**, 4 nodos.
- Instancia Evolution: **`famep-wa`**, `status: open`, dueño `5217731169349@s.whatsapp.net`.
- Teléfono configurado: normaliza a **521 + 10 dígitos** (se valida en Laravel y en n8n).
- Si el teléfono es placeholder o inválido, Laravel omite el envío y registra un warning.
- Cómo inspeccionar ejecuciones de n8n: copiar `database.sqlite` (+ `-wal` y `-shm`) desde `famep-n8n:/home/node/.n8n/` y leerlo desde el contenedor `app` con PDO SQLite.

---

## 11. Operación

### URLs

| Servicio | URL |
|---|---|
| App | `http://localhost:9090` |
| Red local / móvil | `http://192.168.100.118:9090` |
| n8n | `http://localhost:5678` |
| Evolution API | `http://localhost:9091` |
| Adminer (dev) | `http://localhost:9092` |
| PostgreSQL (host) | `localhost:5433` |

### Comandos

```bash
# Levantar entorno dev
docker compose up -d

# Limpiar cachés
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan route:clear
docker compose exec -T app php artisan config:clear

# Regresión de una suite
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/<suite>.php

# Producción (el código va en la imagen)
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

---

## 12. Mapa de archivos clave

| Ruta | Contenido |
|---|---|
| `routes/web.php` | Todas las rutas |
| `app/Http/Controllers/ObservacionCalidadController.php` | Dictámenes + `notificarWhatsApp()` + normalización de teléfono |
| `app/Http/Controllers/ReporteController.php` | Reportes, CSV y filtros (con conversión local) |
| `app/Http/Controllers/DashboardController.php` | KPIs y notificaciones |
| `app/Models/FasePieza.php` | `admiteDictamen()`, `terminada()`, `aprobadaPorCalidad()`, `recalcularEstadoDesdeDictamenes()` |
| `app/Models/Pieza.php` / `Obra.php` | Recálculo de estatus en cascada |
| `app/Support/FechaLocal.php` | Conversión UTC ↔ hora local |
| `app/Services/AuditoriaService.php` | Registro de escrituras; excluye campos sensibles |
| `app/Jobs/SendWhatsAppAlertJob.php` | POST a n8n con timeout y log de errores |
| `config/services.php` | `n8n.webhook_url`, `n8n.notify_phone`, `n8n.timeout` |
| `docker/postgres/init/01-inicial.sql` | Esquema y datos iniciales (fuente de verdad) |
| `docker/web/nginx.conf` | Alias de evidencias, límites y caché |
| `docker-compose.yml` / `.prod.yml` | Dev / producción |
| `resources/views/piezas/show.blade.php` | Fases activas, formulario de dictamen y aviso RF-33 |
| `resources/views/piezas/partials/observaciones.blade.php` | Historial de dictámenes |
| `tests/harness/` | 13 suites + `bootstrap_test.php` |
| `docs/COMPOSICION-APP.md` | Inventario técnico completo |
| `docs/REPORTE-ALERTAS-WHATSAPP-FLUJO.md` | Diagnóstico y fix del flujo de alertas |
| `docs/REPORTE-QR-WHATSAPP.md` | QR, puertos y vinculación |

---

## 13. Pendientes y riesgos conocidos

### Pendientes

1. **Desplegar a producción:** reconstruir la imagen para que tome el fix de alertas, el timeout y el aviso en la UI.
2. **Contrato observable del webhook:** agregar nodo `Respond to Webhook` en n8n y validar la respuesta en Laravel.
3. **Idempotencia:** incluir `dictamen_id` en el payload para evitar duplicados si se reintenta.
4. **Cola opcional:** hoy `dispatchSync` bloquea la petición. `QUEUE_CONNECTION=database` está configurado, pero **no hay worker** corriendo.
5. **Aviso de "dictamen anulado":** si el negocio lo pide, enviar un mensaje distinto al eliminar (hoy solo queda en auditoría).

### Riesgos

- **Sin git:** cualquier cambio es irreversible sin respaldo manual.
- **Esquema manual:** un error en el dump o en una consulta directa no tiene migración que lo corrija.
- **Producción con imagen:** editar archivos en el host **no** actualiza prod hasta reconstruir.
- **Instancia de WhatsApp:** si se desvincula, hay que reescanear el QR; no se guarda en disco.
- **Datos de prueba:** los harness hacen rollback, pero un script manual sin transacción sí puede dejar basura.

---

## 14. Instrucciones para la próxima IA

**Sí hacer:**

- Leer `docs/COMPOSICION-APP.md` antes de cambios grandes.
- Respetar UTC para almacenamiento y `FechaLocal` para presentación.
- Usar el dump como fuente de verdad del esquema.
- Ejecutar la suite relevante y, si toca dictámenes/calidad, `whatsapp_flujo` y `dictamenes_gestion`.
- Mantener los assets locales y el español en la UI.
- Pedir autorización antes de borrar archivos o cambiar reglas de negocio.

**No hacer:**

- No crear migraciones de dominio.
- No introducir CDN ni Vite en runtime.
- No reescribir fechas históricas a hora local.
- No registrar `password_hash` en auditoría.
- No borrar `email.'`, `nombre.PHP_EOL))` ni `CLAUDE.md`.
- No asumir que el código del host está en producción (hay que rebuild).
- No enviar WhatsApp real desde pruebas: el bootstrap ya lo impide con `Http::fake()`.

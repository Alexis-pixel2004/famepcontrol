# FAMEP — Composición de la aplicación y tecnologías

> **Fecha del reporte:** 28 de octubre de 2026
> **Tipo de documento:** inventario técnico (solo lectura, sin cambios de código)
> **Estado:** instantánea del proyecto al momento de su generación

---

## 1. Resumen ejecutivo

FAMEP es una aplicación web **monolítica Laravel** para la gestión de producción y calidad de estructuras metálicas (obras, piezas/marcas, fases, dictámenes de calidad, evidencias fotográficas, operarios y reportes). Se ejecuta completamente **containerizada con Docker**, usa **PostgreSQL 18** como base de datos y cuenta con un **portal público de consulta para clientes** y una **integración de alertas por WhatsApp** vía n8n + Evolution API.

| Aspecto | Definición |
|---|---|
| Tipo | Aplicación web monolítica SSR (Blade) |
| Framework | Laravel 13 |
| Lenguaje | PHP 8.3 |
| Base de datos | PostgreSQL 18 |
| Servidor web | Nginx + PHP-FPM |
| Frontend | Blade + Tailwind CSS + Alpine.js + Chart.js (todo local) |
| Despliegue | Docker Compose (perfiles dev y prod) |
| Automatización externa | n8n + Evolution API (WhatsApp) |

---

## 2. Stack tecnológico (versiones verificadas)

### Backend

| Tecnología | Versión | Notas |
|---|---|---|
| PHP | `^8.3` | Imagen `php:8.3-fpm-alpine` |
| Laravel Framework | **v13.30.1** | `composer.json` declara `^13.17` |
| Laravel Tinker | v3.0.2 | Consola REPL |
| Composer | 2 | Copiado desde `composer:2` en la imagen |
| Extensiones PHP | `pdo_pgsql`, `pgsql`, `intl`, `zip`, `opcache` | Instaladas en el Dockerfile |
| Bcrypt | 12 rondas | `BCRYPT_ROUNDS=12` |

### Base de datos

| Tecnología | Versión | Notas |
|---|---|---|
| PostgreSQL | **18-alpine** | Volumen montado en `/var/lib/postgresql` (requisito de PG 18) |
| Base | `famep_db` | Esquema definido en dump, **no en migraciones** |
| Adminer | `latest` | Solo perfil dev, puerto 9092 |

### Frontend

| Tecnología | Versión | Notas |
|---|---|---|
| Blade | — | 40 vistas, render del lado del servidor |
| Tailwind CSS | 4.x (package.json) | Servido local como JS en `public/vendor/tailwind/tailwind.js` |
| Alpine.js | Local | `public/vendor/alpine/alpine.min.js` |
| Chart.js | **v4.4.4** | `public/vendor/chartjs/chart.umd.min.js`, sin CDN |
| JS propio | — | `public/js/fases-evaluacion.js` |
| Vite | ^8 | Presente en `package.json` pero **no usado en runtime** |

> **Decisión de arquitectura:** no se usa `npm run build` en producción. Todos los assets están versionados en `public/vendor` y `public/js`, y Nginx los sirve con caché de 7 días.

### Automatización e integraciones

| Tecnología | Versión | Puerto |
|---|---|---|
| n8n | Imagen `docker.n8n.io/n8nio/n8n` | 5678 |
| Evolution API | **v1.8.2** | 9091 |
| WhatsApp | Vía Evolution API | Formato México: `521` + 10 dígitos |

### Infraestructura

| Tecnología | Versión | Notas |
|---|---|---|
| Docker + Compose | — | Proyecto `famep` (dev) / `famep-prod` (prod) |
| Nginx | `nginx:alpine` | Puerto host `9090` → contenedor `80` |
| PHP-FPM | `php:8.3-fpm-alpine` | Servicio `app` |

### Herramientas de desarrollo y calidad

| Herramienta | Versión |
|---|---|
| PHPUnit | 12.5.34 |
| Laravel Pint | 1.30.5 |
| Laravel Pail | 1.2.7 |
| Mockery | 1.6.15 |
| NunoMaduro Collision | 8.9.5 |
| FakerPHP | 1.24.1 |

---

## 3. Arquitectura de la aplicación

```
Petición HTTP
     │
     ▼
Nginx (web:9090)
     │  fastcgi
     ▼
PHP-FPM (app)
     │
     ▼
routes/web.php
     │
     ├─ guest            → login
     ├─ público          → portal de consulta por código (sin sesión)
     ├─ auth             → perfil / logout
     └─ auth + jefatura  → panel administrativo
     │
     ▼
Middleware EnsureJefatura (expulsa a clientes del panel)
     │
     ▼
Controladores (12)
     │
     ├─ Modelos Eloquent (13)
     ├─ Servicio de Auditoría (observers)
     └─ Job de WhatsApp (dispatchSync)
     │
     ▼
PostgreSQL 18
```

### Capas y responsabilidades

| Capa | Componentes | Función |
|---|---|---|
| Rutas | `routes/web.php` | Define portal público, auth, perfil y panel `jefatura` |
| Middleware | `EnsureJefatura` | Impide que usuarios rol Cliente entren al panel |
| Controladores | 12 clases | Lógica de obras, piezas, fases, calidad, evidencias, clientes, operarios, reportes y perfil |
| Modelos | 13 clases Eloquent | Entidades y catálogos |
| Servicios | `AuditoriaService` | Decide qué escrituras se registran en `logs_auditoria` |
| Jobs | `SendWhatsAppAlertJob` | Envía alertas a n8n (ejecución síncrona) |
| Providers | `AppServiceProvider` | Registra observers de auditoría |
| Vistas | 40 Blade | UI responsive y componentes reutilizables |

---

## 4. Estructura del proyecto

```
famep-app/
├── app/
│   ├── Http/
│   │   ├── Controllers/      (12)
│   │   └── Middleware/       (EnsureJefatura)
│   ├── Jobs/                 (SendWhatsAppAlertJob)
│   ├── Models/               (13)
│   ├── Providers/            (AppServiceProvider)
│   └── Services/             (AuditoriaService)
├── bootstrap/app.php         (rutas, middleware, excepciones)
├── config/                   (10 archivos)
├── database/
│   ├── migrations/           (solo scaffolding Laravel)
│   └── seeders/
├── docker/
│   ├── app/                  (Dockerfile, entrypoint.sh, uploads.ini)
│   ├── postgres/init/        (01-inicial.sql — fuente de verdad)
│   └── web/nginx.conf
├── public/
│   ├── js/                   (fases-evaluacion.js)
│   └── vendor/               (tailwind, alpine, chartjs)
├── resources/views/          (40 Blade)
├── routes/web.php
├── tests/harness/            (14 archivos: 13 suites + bootstrap)
├── docker-compose.yml
├── docker-compose.prod.yml
├── composer.json / composer.lock
└── package.json
```

### Conteo de archivos

| Elemento | Cantidad |
|---|---|
| Archivos PHP en `app/` | 29 |
| Modelos | 13 |
| Controladores | 12 |
| Vistas Blade | 40 |
| Suites de prueba (harness) | 13 |
| Archivos de configuración | 10 |

---

## 5. Módulos funcionales

| Módulo | Descripción | Roles |
|---|---|---|
| **Autenticación** | Login por correo y contraseña (`password_hash`) | Producción, Calidad |
| **Portal del cliente** | Consulta pública con código `FAMEP-XXXX`, sin sesión | Cliente / público |
| **Dashboard** | KPIs de obras, avance del taller, avance por obra y notificaciones | Producción, Calidad |
| **Obras** | CRUD completo y detalle | Producción, Calidad |
| **Piezas** | CRUD por obra, marca, peso, cantidad, tipo de estructura | Producción, Calidad |
| **Fases** | Seguimiento por pieza, operario, fechas y estatus | Producción, Calidad |
| **Dictámenes de calidad** | Alta, edición y eliminación; resultado Aprobado / Rechazado / Enviado a Reproceso; trazabilidad de edición; inmutables en fases completadas | Calidad |
| **Evidencias** | Fotos asociadas a fases/dictámenes, servidas desde `storage/app/public` | Producción, Calidad |
| **Operarios** | CRUD, habilitación multifase (N:M), especialidad derivada y bloqueo de asignación incompatible | Solo Producción |
| **Reportes** | Avance por obra, calidad, productividad, hallazgos, historial de marca y auditoría; filtros + CSV + gráficas | Según reporte |
| **Perfil** | Cambio de contraseña del propio usuario | Producción, Calidad |
| **Auditoría** | Registro automático de escrituras y bitácora filtrable | Reporte solo Producción |
| **Alertas WhatsApp** | Avisos de rechazo/reproceso vía n8n → Evolution API | Sistema |

### Roles del sistema (`cat_roles`)

| ID | Rol |
|---|---|
| 1 | Jefe de Producción |
| 2 | Jefe de Calidad |
| 3 | Visitante / Cliente |

---

## 6. Modelo de datos

### Tablas de dominio (14)

| Tabla | Propósito |
|---|---|
| `obras` | Órdenes de trabajo |
| `piezas` | Marcas/piezas por obra |
| `fases_pieza` | Fase de una pieza (Armado, Soldadura, Pintura) |
| `observaciones_calidad` | Dictámenes de calidad |
| `evidencias_fotograficas` | Fotos de evidencia |
| `operarios` | Personal de taller |
| `operario_fase` | Habilitación N:M operario ↔ fase |
| `usuarios` | Cuentas de acceso |
| `logs_auditoria` | Bitácora de escrituras |
| `cat_fases` | Catálogo de fases |
| `cat_estatus_fase` | Estados de fase |
| `cat_estatus_pieza` | Estados de pieza |
| `cat_tipos_estructura` | Pesada / Semipesada / Ligera |
| `cat_roles` | Roles del sistema |

### Tablas de infraestructura Laravel (6)

`cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs`.

> **Total: 20 tablas.** El esquema se crea desde `docker/postgres/init/01-inicial.sql`. Las migraciones de `database/migrations` son solo el scaffolding por defecto de Laravel y **no** construyen el dominio.

### Persistencia

| Recurso | Driver |
|---|---|
| Base de datos | `pgsql` |
| Sesiones | `database` |
| Caché | `database` |
| Colas | `database` |
| Archivos/evidencias | `local` → `storage/app/public` |

---

## 7. Infraestructura Docker

### Servicios

| Servicio | Imagen | Puerto host | Perfil |
|---|---|---|---|
| `app` | `famep-app:dev` (build propio) | — | Todos |
| `web` | `nginx:alpine` | **9090** | Todos |
| `db` | `postgres:18-alpine` | **5433** → 5432 | Todos |
| `adminer` | `adminer:latest` | **9092** | Solo dev |
| `n8n` | `docker.n8n.io/n8nio/n8n` | **5678** | Todos |
| `evolution-api` | `evoapicloud/evolution-api:v1.8.2` | **9091** | Todos |

### Red y volúmenes

- **Red:** `famep-net` (bridge).
- **Volúmenes:** `pgdata`, `vendor`, `composer-cache`, `n8n_data`, `evolution_instances`.
- **Postgres 18:** volumen en `/var/lib/postgresql` (no `/var/lib/postgresql/data`).

### Diferencia dev vs prod

| Aspecto | Dev (`docker-compose.yml`) | Prod (`docker-compose.prod.yml`) |
|---|---|---|
| Código | Bind-mount del proyecto | Dentro de la imagen |
| Dependencias | Con `--dev` | `--no-dev` |
| Storage | Del proyecto | Bind-mount `./storage` |
| Adminer | Disponible | Deshabilitado |
| Puerto DB | 5433 expuesto | No expuesto |
| Debug | `APP_DEBUG=true` | `APP_DEBUG=false` |
| Healthcheck | — | PHP-FPM en `127.0.0.1:9000` |

### Configuración de runtime

- `env_file: .env.docker`; sus variables **tienen prioridad** sobre `.env`.
- El entrypoint genera `.env` desde `.env.docker` si no existe, crea directorios de storage, ajusta permisos y limpia cachés antes de arrancar PHP-FPM.

### Zona horaria

- **Almacenamiento y cálculos internos:** `config('app.timezone')` = `UTC`; PHP y PostgreSQL también trabajan en UTC. Las columnas de fecha son `timestamp without time zone` y guardan UTC.
- **Presentación:** `APP_DISPLAY_TIMEZONE=America/Mexico_City` (`config('app.display_timezone')`). La clase `app/Support/FechaLocal.php` centraliza la conversión en vistas, reportes, CSV y alertas de WhatsApp.
- **Filtros por día:** los límites del día local se convierten a UTC con `FechaLocal::inicioDiaUtc()` / `finDiaUtc()` antes de consultar columnas timestamp.
- **Contenedores auxiliares:** n8n y Evolution API usan `TZ=America/Mexico_City` para sus propios logs; n8n mantiene además `GENERIC_TIMEZONE`. El payload de WhatsApp ya viaja con la hora local.
- **Datos históricos:** no se modificaron; siguen almacenados en UTC y se convierten al mostrarse.

### Nginx

- `client_max_body_size 12M` (alineado con validación Laravel de 10 MB).
- Evidencias servidas con `alias` a `storage/app/public` en `/storage/`.
- Assets estáticos con caché de 7 días.
- Bloqueo de archivos ocultos.

---

## 8. Integración WhatsApp (RF-33)

```
Dictamen de Calidad
      │  (solo Rechazado / Enviado a Reproceso)
      ▼
SendWhatsAppAlertJob (dispatchSync)
      │  HTTP POST (timeout configurable, 15 s por defecto)
      ▼
n8n  →  webhook /famep-alerta-calidad
      │
      ▼
Evolution API  →  WhatsApp
```

- Configuración en `config/services.php` (`n8n.webhook_url`, `n8n.notify_phone`, `n8n.timeout`; la variable de entorno del timeout es `N8N_WEBHOOK_TIMEOUT`).
- Validación defensiva del teléfono: formato **521 + 10 dígitos**; se omite el envío con placeholders.
- El campo `fecha` del payload se genera con `FechaLocal::ahora()` (zona de presentación, por defecto `America/Mexico_City`); n8n lo inserta tal cual en el mensaje, sin reinterpretarlo.
- **Cuándo se notifica:** al registrar un dictamen con `Rechazado` o `Enviado a Reproceso`, y al editar uno existente **solo si cambia el resultado**. Editar la descripción o las fotos no reenvía la alerta.
- **Eliminar un dictamen no notifica:** es una corrección administrativa, no un hallazgo nuevo; el rastro queda en `logs_auditoria`. Antes se reenviaba el último dictamen vigente y producía una alerta con fecha de hoy y datos históricos.
- Los dictámenes `Aprobado` no generan alerta (regla RF-33); el formulario lo advierte al usuario.
- Si n8n o Evolution responden 4xx/5xx, el job registra `status` y `body` en el log. Un fallo de envío **no interrumpe** el dictamen.

### Vinculación de WhatsApp (QR)

- El **QR no depende del puerto**: es un token temporal de vinculación de WhatsApp. El puerto solo define la URL donde se solicita.
- La sesión se guarda en el volumen `famep_evolution_instances` (`/evolution/instances`), por lo que **sobrevive a recrear los contenedores**; no hay que revincular al cambiar puertos.
- Para vincular o revincular, abrir `http://localhost:9091/manager` y elegir la instancia `famep-wa`, o consultar `GET /instance/connect/famep-wa` con la API key. Si la instancia ya está conectada, el endpoint responde `{"state":"open"}` y no muestra QR.
- **Desde el 20-nov-2026 también existe `/whatsapp/qr` en el panel**, exclusivo para el Jefe de Calidad. La página consulta Evolution desde el servidor cada 30 s y muestra el QR como data URI; la API key nunca llega al navegador. Las rutas son `whatsapp.qr` y `whatsapp.qr.estado`.
- No guardar el QR como archivo: se renueva cada ~30 s y deja de ser válido. El antiguo `qr.html` de la raíz fue eliminado el 28-oct-2026 por estar expirado y fuera de `public/`.
- Estado esperado: `famep-wa` con `connectionStatus: open` y dueño `5217731169349@s.whatsapp.net`.

---

## 9. Seguridad y control de acceso

| Medida | Implementación |
|---|---|
| Contraseñas | Bcrypt 12 rondas sobre `password_hash` |
| Autenticación | Guard `web` con sesión en base de datos |
| Aislamiento de clientes | `EnsureJefatura` redirige al portal |
| Portal de clientes | Código `FAMEP-XXXX`, sin sesión |
| Autorización por rol | Validaciones en controladores (`esProduccion`, `esCalidad`, `esCliente`) |
| CSRF | Middleware estándar de Laravel |
| Inmutabilidad | Dictámenes de fases completadas solo lectura |
| Auditoría | Registro de escrituras con valores previos/nuevos; nunca contraseñas |
| Archivos ocultos | Nginx los deniega |

> Los secretos (APP_KEY, credenciales de BD, API key de Evolution) están en `.env.docker` y **no se reproducen en este documento**. `docker-compose.yml` usa `env_file: .env.docker` en los servicios `app`, `db` y `evolution-api`; la plantilla sin valores reales es `.env.docker.example`. El traslado conservó los valores originales: PostgreSQL solo aplica `POSTGRES_PASSWORD` al inicializar `pgdata`, así que cualquier rotación requiere un procedimiento aparte.

---

## 10. Pruebas y calidad

- **Harness propio** en `tests/harness/`, ejecutado contra la base real con transacción y rollback (no deja datos).
- **13 suites funcionales** + bootstrap:

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

> **Nota de mantenimiento (28-oct-2026):** la suite `estatus_obra` toma una línea base de KPIs antes de crear datos de prueba y valida el delta, por lo que ya no depende de cuántas obras reales existan. Resultado actual: **453 OK, 0 FAIL**.

> **Aislamiento de red:** `tests/harness/bootstrap_test.php` aplica `Http::fake()` global, así que ninguna suite dispara alertas reales a n8n/WhatsApp. Las suites que necesitan contar peticiones (`dictamenes_gestion`, `whatsapp_flujo`) reinstalan su propio stub.

> `tests/Unit` y `tests/Feature` contienen solo los ejemplos por defecto de Laravel; la cobertura real está en el harness.

---

## 11. Operación

### URLs

| Entorno | URL |
|---|---|
| Host | `http://localhost:9090` |
| Red local / móvil | `http://192.168.100.118:9090` |
| n8n | `http://localhost:5678` |
| Evolution API | `http://localhost:9091` |
| Adminer (dev) | `http://localhost:9092` |

### Comandos habituales

```bash
docker compose up -d
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan route:clear
docker compose exec -T app php artisan config:clear
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/<suite>.php
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

---

## 12. Observaciones

1. **Sin control de versiones.** El directorio no es un repositorio git.
2. **Esquema sin migraciones.** La fuente de verdad de la BD es el dump `docker/postgres/init/01-inicial.sql`.
3. **Vite no se usa en runtime.** Tailwind, Alpine y Chart.js se sirven como archivos locales versionados.
4. **Auditoría activa.** Los observers están registrados y `logs_auditoria` se puebla con las escrituras reales (altas, ediciones y eliminaciones), guardando valores previos/nuevos y excluyendo campos sensibles.
5. **Archivos ajenos en la raíz.** Existen `email.'`, `nombre.PHP_EOL))` y `CLAUDE.md`, que no forman parte del runtime. `qr.html` se eliminó el 28-oct-2026 por contener un QR expirado.
6. **Puertos publicados (28-oct-2026):** `9090` web, `9091` Evolution API, `9092` Adminer, `5433` PostgreSQL y `5678` n8n. Los puertos `8080–8082` quedaron reservados por rangos dinámicos de Windows (Hyper-V/WinNAT), por lo que se movieron para evitar el bloqueo.
7. **Assets locales:** todas las vistas, incluido el login, usan los archivos de `public/vendor` (sin CDN). Corregido el 28-oct-2026.
8. **Healthcheck de producción:** `docker-compose.prod.yml` verifica PHP-FPM en `127.0.0.1:9000`. Corregido el 28-oct-2026 (antes apuntaba a un HTTP inexistente dentro del contenedor `app`).
9. **Zona horaria (28-oct-2026):** la aplicación y la BD permanecen en UTC; la presentación usa `America/Mexico_City` mediante `APP_DISPLAY_TIMEZONE` y `App\Support\FechaLocal`. Se corrigieron dictámenes, reportes, CSV, filtros por día, portal, notificaciones y WhatsApp. Los datos históricos no se alteraron.

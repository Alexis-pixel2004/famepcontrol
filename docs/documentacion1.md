# Documentación del proyecto FAMEP

> **Documento maestro del proyecto.**
> **Fecha:** 10-nov-2026 · **Versión del sistema:** Laravel 13.30.1 · **Rama:** `dev`

---

## Control del documento

| Campo | Valor |
|---|---|
| Proyecto | FAMEP — Control de Producción y Calidad |
| Tipo | Sistema web monolítico de gestión operativa |
| Documento | Documentación integral del proyecto |
| Versión | 1.0 |
| Fecha | 10-nov-2026 |
| Estado | Vigente al corte indicado |
| Audiencia | Desarrollo, operación, calidad, dirección técnica y nuevo personal |

---

## 1. Resumen ejecutivo

FAMEP es una aplicación web para digitalizar y controlar la producción de estructuras metálicas en taller. Permite registrar obras, desglosarlas en piezas o marcas, dar seguimiento a las fases productivas (**Armado, Soldadura y Pintura**), asignar operarios habilitados, emitir dictámenes de calidad con evidencia fotográfica y consultar el avance desde un portal público para clientes.

El sistema se compone de:

- Un **panel administrativo** para Jefatura de Producción y Jefatura de Calidad.
- Un **portal público** para clientes, sin inicio de sesión, mediante código `FAMEP-XXXX`.
- Un módulo de **reportes** con filtros, gráficas y exportación CSV.
- Una **bitácora de auditoría** automática de escrituras.
- Una integración de **alertas por WhatsApp** vía n8n y Evolution API.
- Un **harness de pruebas** propio que corre contra PostgreSQL con transacción y rollback.

| Indicador | Valor |
|---|---|
| Framework | Laravel 13.30.1 |
| Lenguaje | PHP 8.3 |
| Base de datos | PostgreSQL 18 |
| Servidor | Nginx + PHP-FPM sobre Docker |
| Frontend | Blade + Tailwind CSS + Alpine.js + Chart.js, todo local |
| Módulos principales | 13 |
| Rutas registradas | 57 |
| Vistas Blade | 43 |
| Tablas | 20 (14 de dominio + 6 de infraestructura) |
| Suites de prueba | 16 |
| Checks | 685 |
| Resultado al corte | 685 OK, 0 FAIL |

> Conteos actualizados el 22-nov-2026: se agregó el orden secuencial de fases, la edición/eliminación de asignaciones (3 rutas, 1 vista y 1 suite), el cierre de fases conserva el operario ya asignado sin volver a elegirlo manteniendo todas las validaciones, el historial tiene un botón de actualización para Producción y Calidad, el área de clientes permite activar/desactivar el código y eliminar cuentas sin obras ni historial (3 rutas y 1 vista), y el login muestra el logo local `logo-famep.png` sobre el título. Las suites existentes se reanclaron a la semilla real. Se agregó RN-24: la fecha de compromiso debe ser posterior a la fecha de inicio (no se permite el mismo día), con validación en alta y edición, ayuda accesible y 5 checks nuevos. Se agregó RN-25: la cantidad queda fija en 1 al agregar piezas, con campo oculto, merge en el servidor y 4 checks nuevos. Se agregó RN-26: **Producción conserva su panel histórico con los dictámenes de Calidad**, mientras Calidad estrena un panel distinto de actividad del taller (fase agregada y pieza nueva desde `logs_auditoria`), sin sus propias acciones; si la pieza se elimina, el aviso se conserva como `registro_eliminado` sin enlace roto. Un mismo endpoint devuelve el payload según el rol y cada campana usa su propio componente. Se agregó RN-27: los errores de validación y los bloqueos del servidor se muestran en el detalle de obra (marca duplicada, campo con `aria-invalid` y valor conservado). Se agregó RN-28: **todas las eliminaciones** (obras, piezas, operarios, clientes, asignaciones y dictámenes) usan el mismo modal propio accesible en vez del `confirm` del navegador. La regresión completa quedó en 17/17 suites limpias.

---

## 2. Introducción

### 2.1 Contexto

El taller de estructuras metálicas requiere controlar, para cada obra:

- qué piezas o marcas se fabrican;
- en qué fase va cada pieza;
- qué operario la trabaja;
- qué dictamen emitió Calidad;
- qué evidencia fotográfica respalda el dictamen;
- cuánto avance lleva la obra.

Antes de FAMEP, ese seguimiento dependía de registros dispersos y comunicación informal, lo que dificultaba conocer el avance real, detectar reprocesos y conservar evidencia de calidad.

### 2.2 Problemática

| Problema | Consecuencia |
|---|---|
| Avance sin trazabilidad | No se sabe con certeza en qué fase está cada marca |
| Asignaciones duplicadas | Un operario podía aparecer en varias tareas activas |
| Dictámenes sin respaldo | Calidad no tenía evidencia fotográfica vinculada |
| Reprocesos sin seguimiento | Se perdía el historial de rechazos y correcciones |
| Cliente sin visibilidad | El cliente dependía de llamadas o reportes manuales |
| Sin bitácora | No había registro de quién creó, editó o eliminó información |

### 2.3 Objetivo general

Centralizar la gestión de producción y calidad de estructuras metálicas en una aplicación web única, trazable y consultable.

### 2.4 Objetivos específicos

1. Registrar obras y piezas con datos de fabricación.
2. Controlar las fases productivas por pieza y operario.
3. Impedir asignaciones incompatibles o simultáneas de operarios.
4. Registrar dictámenes de calidad con evidencia fotográfica.
5. Propagar automáticamente el estatus de fase a pieza y obra.
6. Ofrecer un portal público de consulta para clientes.
7. Generar reportes operativos y de calidad exportables.
8. Auditar automáticamente las escrituras relevantes.
9. Notificar por WhatsApp los rechazos y reprocesos.
10. Mantener una suite de regresión que proteja las reglas anteriores.

### 2.5 Alcance

**Incluido:**

- Autenticación por correo y contraseña.
- Panel de Producción y Calidad.
- Portal público de clientes.
- Obras, piezas, fases, dictámenes, evidencias y operarios.
- Reportes y auditoría.
- Alertas de WhatsApp.
- Vinculación de WhatsApp por QR (solo Jefe de Calidad).
- Pruebas de regresión.

**Excluido:**

- Facturación, compras, inventario o nómina.
- Aplicación móvil nativa.
- Firma digital o certificados.
- Restricción de altas de obras/piezas a Producción (E7, pendiente de decisión).
- Módulo de notificaciones más amplio (bitácora, idempotencia, cola y exportación del workflow de n8n): fuera de alcance por ahora.

---

## 3. Descripción del sistema

### 3.1 Módulos

| Módulo | Función | Roles |
|---|---|---|
| Autenticación | Login y logout con sesión | Producción, Calidad |
| Portal del cliente | Consulta pública por código `FAMEP-XXXX` | Cliente / público |
| Dashboard | KPIs de obras, avance del taller y notificaciones | Producción, Calidad |
| Obras | Alta, edición, detalle y eliminación | Producción; consulta Calidad |
| Piezas | Alta y edición de marcas por obra | Producción; consulta Calidad |
| Fases | Registro de avance, operario, fechas y estatus | Producción; consulta Calidad |
| Dictámenes | Inspección con resultado y comentario | Calidad |
| Evidencias | Fotos de fase o de dictamen | Calidad |
| Operarios | Alta, edición, habilitación multifase y baja lógica | Producción |
| Reportes | Avance, calidad, productividad, hallazgos, historial y auditoría | Producción y Calidad; auditoría solo Producción |
| Auditoría | Bitácora automática de escrituras | Producción |
| Alertas WhatsApp | Notificación de rechazo o reproceso | Sistema |
| Vinculación WhatsApp | Consulta y renovación del QR de la instancia `famep-wa` | Solo Calidad |

### 3.2 Actores y roles

| ID | Rol | Descripción | Acceso |
|---|---|---|---|
| 1 | Jefe de Producción | Administra obras, piezas, fases, operarios y reportes | Panel completo; no dictamina |
| 2 | Jefe de Calidad | Inspecciona, dictamina y adjunta evidencias | Panel, sin gestión de operarios |
| 3 | Visitante / Cliente | Consulta el avance de sus obras | Solo `/consulta` |

> Los permisos finos se validan en los controladores con `esProduccion()`, `esCalidad()` y `esCliente()`. El middleware `EnsureJefatura` solo expulsa al rol 3 del panel.

### 3.3 Cuentas iniciales

El seeder crea cuentas de demostración:

| Rol | Correo |
|---|---|
| Producción | `produccion@famep.com` |
| Calidad | `calidad@famep.com` |

> La contraseña de demostración está definida en `database/seeders/DatabaseSeeder.php`. **Debe cambiarse en producción.**

---

## 4. Requerimientos

### 4.1 Requerimientos funcionales

> Numeración propuesta para esta documentación; el código conserva la referencia original **RF-33** para WhatsApp.

| Código | Requerimiento | Estado |
|---|---|---|
| RF-01 | Autenticar usuarios por correo y contraseña | Implementado |
| RF-02 | Redirigir al portal a usuarios con rol Cliente | Implementado |
| RF-03 | Calcular avance de obra y taller por fases completadas | Implementado |
| RF-04 | Gestionar obras con código único y cliente asignado | Implementado |
| RF-05 | Gestionar piezas/marcas por obra | Implementado |
| RF-06 | Registrar fases por pieza y asignar operario | Implementado |
| RF-07 | Impedir que un operario tenga dos tareas activas | Implementado |
| RF-08 | Emitir dictámenes con resultado Aprobado, Rechazado o En Reproceso | Implementado |
| RF-09 | Adjuntar evidencia fotográfica a fase o dictamen | Implementado |
| RF-10 | Impedir dictámenes en fases cerradas | Implementado |
| RF-11 | Gestionar operarios y habilitación multifase | Implementado |
| RF-12 | Consultar obras del cliente con código de acceso | Implementado |
| RF-13 | Generar reportes con filtros, gráficas y CSV | Implementado |
| RF-14 | Auditar altas, ediciones y eliminaciones | Implementado |
| RF-15 | Cambiar la contraseña propia | Implementado |
| RF-16 | Vincular la instancia de WhatsApp desde el panel (solo Calidad) | Implementado |
| RF-33 | Notificar por WhatsApp rechazos y reprocesos | Implementado |

### 4.2 Requerimientos no funcionales

| Código | Requerimiento |
|---|---|
| RNF-01 | Aplicación web SSR con Blade, responsive y sin SPA |
| RNF-02 | Base de datos PostgreSQL 18 |
| RNF-03 | Despliegue con Docker Compose en perfiles dev y prod |
| RNF-04 | Assets locales, sin CDN ni compilación Vite en runtime |
| RNF-05 | Interfaz, mensajes y documentación en español |
| RNF-06 | Almacenamiento en UTC y presentación en `America/Mexico_City` |
| RNF-07 | Contraseñas con Bcrypt de 12 rondas |
| RNF-08 | Autorización por rol en controladores |
| RNF-09 | Auditoría de escrituras sin datos sensibles |
| RNF-10 | Evidencias servidas por Nginx desde `storage/app/public` |
| RNF-11 | Accesibilidad AA: foco visible, contraste, `aria-*` y semántica HTML |
| RNF-12 | Suite de regresión con transacción y rollback |
| RNF-13 | Subida máxima de 10 MB por imagen (12 MB en Nginx) |

### 4.3 Reglas de negocio

| Código | Regla |
|---|---|
| RN-01 | Una fase `Completada` es terminal e inmutable |
| RN-02 | `Aprobado` no cierra la fase: la deja en `Aprobada por Calidad` |
| RN-03 | Solo Producción cierra una fase, y solo si Calidad ya aprobó |
| RN-04 | El estado de la fase se deriva del último dictamen por `id` |
| RN-05 | Un operario solo puede tener una fase activa a la vez |
| RN-06 | Ocupan al operario: `En Proceso`, `En Reproceso` y `Aprobada por Calidad` |
| RN-07 | Un operario inactivo no puede recibir trabajo nuevo |
| RN-08 | No se puede desactivar ni deshabilitar a un operario con tarea abierta |
| RN-09 | No se puede reactivar una fase si el operario ya tiene otra tarea |
| RN-10 | La marca es única por obra y se normaliza a mayúsculas |
| RN-11 | No se reasignan obras que ya pertenecen a otro cliente |
| RN-12 | Solo usuarios rol 3 pueden ser cliente de una obra |
| RN-13 | Solo se eliminan obras `Pendientes` y piezas sin fases |
| RN-14 | Los dictámenes de una fase `Completada` son de solo lectura |
| RN-15 | Eliminar un dictamen no envía WhatsApp |
| RN-16 | Editar un dictamen solo notifica si cambia el resultado |
| RN-17 | El portal solo muestra obras del cliente dueño del código |
| RN-18 | Al registrar una fase, todas las anteriores por `orden_secuencia` deben estar `Completada` (Armado → Soldadura → Pintura) |
| RN-19 | Solo Producción puede editar o eliminar asignaciones; editar cambia únicamente el operario |
| RN-20 | No se elimina una asignación cerrada/terminada ni una fase con dictámenes o evidencias |
| RN-21 | Al cerrar una fase aprobada sin enviar `operario_id`, se conserva el operario asignado y se valida igual que uno elegido manualmente |
| RN-22 | El código de acceso del cliente puede activarse o desactivarse; inactivo no da acceso al portal |
| RN-23 | No se elimina un cliente con obras asignadas ni con registros que lo referencien; se desactiva en su lugar |
| RN-24 | La fecha de compromiso de una obra debe ser posterior a la fecha de inicio; no se permite el mismo día |
| RN-25 | Al agregar una pieza, la cantidad queda fija en 1 |
| RN-26 | Producción conserva su panel de dictámenes de Calidad; Calidad tiene un panel distinto de actividad del taller (fase agregada y pieza nueva), sin sus propios dictámenes |
| RN-27 | Los errores de validación y bloqueo se muestran en pantalla en el detalle de obra |
| RN-28 | Todas las eliminaciones (obras, piezas, operarios, clientes, asignaciones y dictámenes) se confirman con un modal de la plataforma, no con el `confirm` del navegador |

---

## 5. Arquitectura

### 5.1 Vista general

```
Navegador
   │
   ▼
Nginx :9090 ──► PHP-FPM (contenedor app)
   │
   ▼
routes/web.php
   ├─ guest            → /login
   ├─ público          → /consulta (código FAMEP-XXXX)
   ├─ auth             → /perfil, /logout
   └─ auth + jefatura  → panel administrativo
   │
   ▼
Controladores ──► Modelos Eloquent ──► PostgreSQL 18
   │                  │
   │                  ├─ AuditoriaService (observers)
   │                  └─ SendWhatsAppAlertJob (dispatchSync)
   │
   └─ Vistas Blade + Tailwind/Alpine/Chart.js locales
```

### 5.2 Capas

| Capa | Componentes | Responsabilidad |
|---|---|---|
| Rutas | `routes/web.php` | Define acceso público, autenticado y jefatura |
| Middleware | `EnsureJefatura` | Expulsa clientes del panel |
| Controladores | 12 clases | Reglas de negocio, permisos y respuestas |
| Modelos | 13 clases | Entidades, relaciones y cálculos derivados |
| Servicios | `AuditoriaService` | Registro de escrituras |
| Jobs | `SendWhatsAppAlertJob` | Envío de alertas a n8n |
| Providers | `AppServiceProvider` | Registro de observers |
| Support | `FechaLocal` | Conversión UTC ↔ hora local |
| Vistas | 40 Blade | Interfaz de usuario |
| Pruebas | `tests/harness` | Regresión funcional |

### 5.3 Stack tecnológico

| Capa | Tecnología | Versión / configuración |
|---|---|---|
| Backend | Laravel | 13.30.1 |
| Lenguaje | PHP | 8.3 |
| Base de datos | PostgreSQL | 18-alpine |
| Servidor web | Nginx | `nginx:alpine` |
| PHP runtime | PHP-FPM | `php:8.3-fpm-alpine` |
| Frontend | Blade | Render del lado del servidor |
| Estilos | Tailwind CSS | 4.x, servido localmente |
| Interactividad | Alpine.js | Local |
| Gráficas | Chart.js | 4.4.4, local |
| Build | Vite | 8.x, no usado en runtime |
| Automatización | n8n | Webhook `/famep-alerta-calidad` |
| Mensajería | Evolution API | 1.8.2, instancia `famep-wa` |
| Contenedores | Docker Compose | Proyecto `famep` / `famep-prod` |
| Pruebas | Harness propio | 17 suites |

### 5.4 Estructura de carpetas

```
famep-app/
├── app/
│   ├── Http/
│   │   ├── Controllers/      (12)
│   │   └── Middleware/       (EnsureJefatura)
│   ├── Jobs/                 (SendWhatsAppAlertJob)
│   ├── Models/               (13)
│   ├── Providers/            (AppServiceProvider)
│   ├── Services/             (AuditoriaService)
│   └── Support/              (FechaLocal)
├── bootstrap/app.php
├── config/                   (10 archivos)
├── database/
│   ├── migrations/           (solo scaffolding)
│   └── seeders/
├── docker/
│   ├── app/                  (Dockerfile, entrypoint, uploads.ini)
│   ├── postgres/init/        (01-inicial.sql)
│   └── web/nginx.conf
├── docs/
├── public/
│   ├── js/
│   └── vendor/               (tailwind, alpine, chartjs)
├── resources/views/          (42)
├── routes/web.php
├── storage/app/public/       (evidencias)
├── tests/harness/            (17 suites + bootstrap)
├── docker-compose.yml
├── docker-compose.prod.yml
└── composer.json
```

### 5.5 Decisiones de arquitectura no negociables

1. **El dump es la fuente de verdad del esquema.** No se crean migraciones de dominio; el esquema vive en `docker/postgres/init/01-inicial.sql`.
2. **`.env.docker` manda.** `env_file: .env.docker` tiene prioridad sobre `.env`.
3. **Sin Vite en runtime.** Tailwind, Alpine y Chart.js se sirven desde `public/vendor`.
4. **Sin CDN.** Todos los assets son locales.
5. **UTC para almacenamiento y `FechaLocal` para presentación.**
6. **Auditoría solo de escrituras**, con valores previos/nuevos y sin `password_hash`.
7. **Dev y prod separados por perfiles de Docker.** En prod el código va dentro de la imagen.
8. **Evidencias servidas por Nginx** mediante alias a `storage/app/public`.

---

## 6. Modelo de datos

### 6.1 Entidades

| Tabla | Propósito |
|---|---|
| `obras` | Órdenes de trabajo |
| `piezas` | Marcas o piezas por obra |
| `fases_pieza` | Fase de una pieza |
| `observaciones_calidad` | Dictámenes de calidad |
| `evidencias_fotograficas` | Fotos de evidencia |
| `operarios` | Personal de taller |
| `operario_fase` | Habilitación N:M operario ↔ fase |
| `usuarios` | Cuentas de acceso |
| `logs_auditoria` | Bitácora de escrituras |
| `cat_fases` | Catálogo de fases |
| `cat_estatus_fase` | Estados de fase |
| `cat_estatus_pieza` | Estados de pieza |
| `cat_tipos_estructura` | Pesada, Semipesada, Ligera |
| `cat_roles` | Roles del sistema |

**Infraestructura Laravel:** `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs`.

**Total: 20 tablas.**

### 6.2 Diccionario de datos

#### `obras`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `codigo_obra` | varchar(30), único | Código de la obra |
| `nombre_obra` | varchar(150) | Nombre |
| `cliente` | varchar(150) | Cliente textual |
| `fecha_inicio` | date | Inicio |
| `fecha_compromiso` | date | Compromiso |
| `estatus` | varchar(20) | `Pendiente`, `Activa`, `Finalizada` |
| `cliente_usuario_id` | integer FK | Cuenta de cliente con acceso al portal |
| `created_at` | timestamp | Alta |

#### `piezas`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `obra_id` | integer FK | Obra |
| `tipo_estructura_id` | integer FK | Pesada, Semipesada o Ligera |
| `estatus_id` | integer FK | Estatus derivado |
| `clave_marca_pieza` | varchar(50) | Marca; única por obra |
| `peso_kg` | numeric(10,2) | Peso |
| `cantidad` | integer | Cantidad |
| `created_at` | timestamp | Alta |

#### `fases_pieza`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `pieza_id` | integer FK | Pieza |
| `fase_id` | integer FK | Armado, Soldadura o Pintura |
| `operario_id` | integer FK, nullable | Operario asignado |
| `usuario_captura_id` | integer FK | Usuario que registró |
| `estatus_fase_id` | integer FK | Estado actual |
| `fecha_inicio` | timestamp | Inicio |
| `fecha_dictamen` | timestamp | Último dictamen |
| `fecha_termino` | timestamp | Cierre |
| `created_at` | timestamp | Alta |

**Restricciones:** única `(pieza_id, fase_id)`; índice por `estatus_fase_id`.

#### `observaciones_calidad`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `fase_pieza_id` | integer FK | Fase inspeccionada |
| `usuario_id` | integer FK | Inspector |
| `descripcion` | text | Comentario |
| `severidad` | varchar(20) | `Media` por defecto |
| `corregido` | boolean | Marca de corrección |
| `resultado` | varchar(50) | `Aprobado`, `Rechazado`, `Enviado a Reproceso` |
| `editado` | boolean | Si fue modificado |
| `editado_at` | timestamp | Fecha de edición |
| `editado_por` | integer FK | Usuario que editó |
| `created_at` | timestamp | Alta |

#### `evidencias_fotograficas`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `fase_pieza_id` | bigint FK | Fase |
| `observacion_calidad_id` | integer FK, nullable | Dictamen asociado |
| `ruta_archivo` | varchar(255) | Ruta en `storage/app/public` |
| `created_at` | timestamp | Alta |

**Restricción:** `observacion_calidad_id` con `ON DELETE CASCADE`.

#### `operarios`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `clave_empleado` | varchar(20), único | Clave |
| `nombre` | varchar(100) | Nombre |
| `apellido` | varchar(100) | Apellido |
| `especialidad` | varchar(50) | Respaldo histórico |
| `activo` | boolean | Alta/baja lógica |
| `created_at` | timestamp | Alta |

#### `operario_fase`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `operario_id` | integer FK | Operario |
| `fase_id` | integer FK | Fase habilitada |

**Restricción:** única `(operario_id, fase_id)`.

#### `usuarios`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `rol_id` | integer FK | Rol |
| `nombre` | varchar(100) | Nombre |
| `email` | varchar(150), único | Correo |
| `password_hash` | varchar(255) | Hash Bcrypt |
| `activo` | boolean | Estado |
| `codigo_acceso` | varchar(30), único parcial | Código `FAMEP-XXXX` |
| `created_at` | timestamp | Alta |

#### `logs_auditoria`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | integer PK | Identificador |
| `usuario_id` | integer FK | Autor |
| `accion` | varchar(100) | `creado`, `editado`, `eliminado` |
| `tabla_afectada` | varchar(50) | Tabla |
| `registro_id` | integer | Registro afectado |
| `datos_previos` | jsonb | Valores anteriores |
| `datos_nuevos` | jsonb | Valores nuevos |
| `fecha_hora` | timestamp | Momento |

### 6.3 Catálogos

| Catálogo | Valores |
|---|---|
| `cat_fases` | Armado (1), Soldadura (2), Pintura (3) — `orden_secuencia` obligatorio |
| `cat_estatus_fase` | En Proceso, En Reproceso, Rechazada, Aprobada por Calidad, Completada |
| `cat_estatus_pieza` | Pendiente, En Proceso, En Revisión, Detenida, Aprobada |
| `cat_tipos_estructura` | Pesada, Semipesada, Ligera |
| `cat_roles` | Jefe de Producción, Jefe de Calidad, Visitante/Cliente |

### 6.4 Banderas de estado de fase

| Estado | `es_completada` | `es_rechazada` | `en_reproceso` | `bloquea_captura` |
|---|---|---|---|---|
| En Proceso | No | No | No | No |
| En Reproceso | No | No | Sí | No |
| Rechazada | No | Sí | No | **Sí** |
| Aprobada por Calidad | No | No | No | No |
| Completada | **Sí** | No | No | **Sí** |

### 6.5 Relaciones

```
usuarios (rol 3) 1 ─── N obras
obras            1 ─── N piezas
piezas           1 ─── N fases_pieza
cat_fases        1 ─── N fases_pieza
operarios        1 ─── N fases_pieza
operarios        N ─── N cat_fases   (operario_fase)
fases_pieza      1 ─── N observaciones_calidad
fases_pieza      1 ─── N evidencias_fotograficas
observaciones_calidad 1 ─── N evidencias_fotograficas
usuarios         1 ─── N logs_auditoria
```

### 6.6 Estatus derivados

**Pieza:**

| Condición | Estatus |
|---|---|
| Sin fases registradas | Pendiente |
| Alguna fase rechazada | Detenida |
| Alguna fase en reproceso | En Revisión |
| Las 3 fases del catálogo completadas | Aprobada |
| Resto | En Proceso |

**Obra:**

| Condición | Estatus |
|---|---|
| Sin piezas o sin ninguna con fases | Pendiente |
| Todas las piezas aprobadas | Finalizada |
| Resto | Activa |

> Los estatus derivados se recalculan en cascada y no se auditan como cambios manuales.

---

## 7. Funcionamiento por módulo

### 7.1 Autenticación

1. El usuario entra a `/login`.
2. Ingresa correo y contraseña.
3. Laravel valida contra `usuarios.password_hash` con Bcrypt.
4. Si el rol es Cliente, se redirige a `/consulta`.
5. Si es Producción o Calidad, se redirige a `/dashboard`.
6. El logout invalida la sesión.

### 7.2 Dashboard

Muestra:

- Obras activas, pendientes, finalizadas y total.
- Avance global del taller: fases completadas / (fases del catálogo × piezas).
- Avance por obra.
- Fase actual por pieza.
- Últimas 5 obras.
- Panel de notificaciones por rol (RN-26):
  - **Producción:** conserva su panel histórico «Notificaciones de Calidad» con los últimos 8 dictámenes (`resultado`, `descripción`, inspector, obra, marca y enlace). Componente `dropdown-notificaciones`.
  - **Calidad:** panel «Actividad del Taller» con fases agregadas y piezas nuevas leídas desde `logs_auditoria`, excluyendo sus propias acciones. Componente `dropdown-actividad-taller`.
  - **Portal:** sin campana.
- Un solo endpoint JSON `/api/notificaciones-calidad` (`api.notificaciones`) devuelve el payload correspondiente al rol; otros roles reciben `[]`.
- Si la pieza o fase ya no existe, el aviso de Calidad se conserva como `registro_eliminado`, se reconstruye obra/marca desde `datos_nuevos` y se omite `url_pieza`.
- El `localStorage` del badge es por usuario, para que no se mezclen los contadores entre roles en el mismo navegador.

### 7.3 Obras

**Alta:**

- Código único.
- Nombre y cliente textual.
- Fechas de inicio y compromiso; el compromiso debe ser posterior al inicio (RN-24).
- Cuenta de cliente opcional (solo rol 3).
- Estatus inicial `Pendiente`.

**Edición:** solo Producción, aplicando la misma regla de fechas (RN-24).

**Eliminación:** solo Producción y solo si la obra está `Pendiente` (sin avance). La confirmación se hace con el modal compartido de la plataforma (`x-confirmar-eliminacion`), accesible con teclado y lector de pantalla; ya no se usa el `confirm` del navegador (RN-28). Lo mismo aplica al eliminar piezas, operarios, clientes, asignaciones y dictámenes.

**Detalle:** piezas, KPIs, avance, piezas liberadas, en reproceso y pendientes. Los errores de validación y los bloqueos del servidor se muestran en rojo dentro de la vista (RN-27).

### 7.4 Piezas

**Alta:**

- Marca normalizada a mayúsculas.
- Única por obra; el rechazo se muestra en pantalla con el mensaje «Ya existe una pieza con esa marca en esta obra.» y el campo conserva lo escrito (RN-27).
- Tipo de estructura y peso; cantidad fija en 1 (RN-25).
- Estatus inicial `Pendiente`.

**Edición:**

- Si no tiene fases: se puede cambiar marca, tipo, peso y cantidad.
- Si ya tiene fases: solo peso y cantidad.

**Eliminación:** solo si no tiene fases registradas.

### 7.5 Fases y asignación de operarios

1. Producción selecciona pieza, fase, operario y estado (`En Proceso` o `Completada`).
2. El sistema valida, en este orden:
   - fase terminal o cerrada;
   - operario repetido en la misma fase en proceso;
   - habilitación del operario para la fase;
   - fase aprobada por Calidad sin intención de cierre;
   - cierre sin aprobación de Calidad;
   - **orden secuencial**: todas las fases con `orden_secuencia` menor deben existir y estar `Completada`;
   - operario ocupado en otra tarea activa.
3. La escritura se ejecuta en transacción con `lockForUpdate` sobre la pieza y el operario, para serializar altas simultáneas que intenten adelantarse.
4. Se recalcula el estatus de la pieza y de la obra.

**Cierre sin reelegir operario:** cuando la fase ya está `Aprobada por Calidad`, el formulario preselecciona al operario asignado y el controlador lo reutiliza si no se envía `operario_id`. Ese valor pasa por las mismas validaciones de operario activo y habilitado; si quedó inactivo, se usa “Editar asignación” antes de cerrar. El fallback no aplica a altas nuevas ni a fases `En Proceso`.

**Edición de asignaciones:** solo Producción. Desde `piezas/show` se accede a `piezas/fases/edit`; el formulario permite cambiar únicamente el operario (nunca la fase ni el estatus), valida habilitación y que el operario destino no tenga otra tarea activa. Las fases `Completada`/cerradas no se pueden editar.

**Eliminación de asignaciones:** solo Producción. Se bloquea si la fase está cerrada/terminada o si ya tiene dictámenes o evidencias, para no borrar historial ni dejar fotos huérfanas. Después de borrar se recalculan pieza y obra.

**Actualización del historial:** `piezas/show` muestra el botón “Actualizar historial” tanto para Producción como para Calidad. Usa Alpine local y `window.location.reload()`; expone `aria-label`, icono decorativo con `aria-hidden`, foco visible y estado deshabilitado mientras se recarga. No hay endpoint nuevo ni auto-refresh que pueda interrumpir una captura.

**Regla clave:** un operario solo puede tener una fase activa. Se excluye la fase que se está guardando para permitir retomar un reproceso con el mismo operario. Además, el avance siempre sigue Armado → Soldadura → Pintura.

### 7.6 Dictámenes de calidad

1. Calidad elige una fase que admita dictamen (`En Proceso` o `En Reproceso`).
2. Registra descripción, resultado y foto opcional.
3. El sistema:
   - bloquea la fase con `lockForUpdate`;
   - rechaza fases terminales o ya aprobadas;
   - crea el dictamen;
   - guarda la evidencia;
   - actualiza el estado de la fase y `fecha_dictamen`;
   - recalcula pieza y obra.
4. Si el resultado es `Rechazado` o `Enviado a Reproceso`, dispara WhatsApp.

**Edición:**

- Solo Calidad.
- No se permite en fases `Completadas`.
- Se recalcula el estado desde el último dictamen.
- Solo notifica si cambió el resultado.
- Marca `editado`, `editado_at` y `editado_por` solo si hubo cambios reales.

**Eliminación:**

- Solo Calidad.
- No se permite en fases `Completadas`.
- Borra evidencias físicas y registros.
- Recalcula el estado.
- **No envía WhatsApp.**

### 7.7 Evidencias

- Solo Calidad.
- La fase debe existir y no estar cerrada.
- Formatos: JPEG, PNG, JPG, WEBP.
- Máximo 5 MB en carga independiente; 10 MB al dictaminar.
- Se guardan en `storage/app/public/evidencias`.

### 7.8 Portal del cliente

1. El cliente entra a `/consulta`.
2. Ingresa su código `FAMEP-XXXX`.
3. El sistema busca un usuario rol 3, activo y con ese código.
4. Muestra sus obras con avance, marcas y fechas.
5. El detalle de una obra valida que pertenezca al cliente; si no, responde 404.

**Gestión del código (área administrativa):** el listado de clientes muestra el estado `Activo`/`Inactivo` y las acciones “Editar” y “Eliminar”. Editar cambia únicamente el campo `activo`; no regenera el código y se puede reactivar cuando se necesite. Eliminar solo se permite si la cuenta no tiene obras asignadas ni registros que la referencien; de lo contrario se bloquea con un mensaje que sugiere desactivarla. Ambos cambios se registran en auditoría sobre `usuarios`.

**Traducción de estados para el cliente:**

| Interno | Visible |
|---|---|
| Pendiente | Por iniciar |
| Activa | En fabricación |
| Finalizada | Terminada |
| En Revisión | En espera de Calidad |
| Detenida | Detenida |
| Aprobada | Terminada |
| En Proceso (fase) | En trabajo |
| En Reproceso | En corrección |
| Completada | Terminada |

### 7.9 Reportes

| Reporte | Contenido | Roles |
|---|---|---|
| Avance por obra | Cumplimiento de fases, marcas liberadas y semáforo de fechas | Producción, Calidad |
| Dictámenes de calidad | Resultados, rechazos, reprocesos y ediciones | Producción, Calidad |
| Productividad por operario | Fases cerradas por operario y especialidad | Producción, Calidad |
| Hallazgos por fase y obra | Concentración de hallazgos y rechazos | Producción, Calidad |
| Historial de marca | Bitácora completa de una marca | Producción, Calidad |
| Bitácora de auditoría | Altas, ediciones y eliminaciones con valores previos/nuevos | Solo Producción |

Características: filtros por obra, fecha y otros criterios; exportación CSV; gráficas con Chart.js; conversión de fechas a hora local.

### 7.10 Auditoría

- Se activa por observers en `AppServiceProvider`.
- Modelos auditados: `Obra`, `Pieza`, `FasePieza`, `ObservacionCalidad`, `Operario`.
- Acciones: `creado`, `editado`, `eliminado`.
- Guarda valores previos y nuevos.
- Ignora campos derivados y `password_hash`.
- Si la escritura falla, no interrumpe la operación.
- El reporte es solo para Producción.

### 7.11 Alertas WhatsApp (RF-33)

```
Dictamen Rechazado / Enviado a Reproceso
        │
        ▼
ObservacionCalidadController::notificarWhatsApp()
        │  valida teléfono 521 + 10 dígitos
        ▼
SendWhatsAppAlertJob (dispatchSync, timeout 15 s)
        │  POST JSON
        ▼
n8n: webhook /famep-alerta-calidad
        │  valida ^521[0-9]{10}$
        ├─ válido   → Evolution API → WhatsApp
        └─ inválido → "Teléfono omitido"
```

**Payload:**

```json
{
  "obra": "Nave Industrial TechPark",
  "marca": "TB1",
  "fase": "Armado",
  "resultado": "Enviado a Reproceso",
  "inspector": "Ana Laura Torres",
  "observacion": "Descripción del hallazgo",
  "fecha": "10/11/2026 14:30",
  "telefono_destino": "521XXXXXXXXXX"
}
```

**Comportamiento:**

| Acción | ¿Notifica? |
|---|---|
| Dictaminar Rechazado / Reproceso | Sí |
| Dictaminar Aprobado | No |
| Editar solo descripción o fotos | No |
| Cambiar resultado a rechazo/reproceso | Sí |
| Eliminar dictamen | No |

Si n8n o Evolution fallan, se registra el error y **no se interrumpe** el dictamen.

### 7.12 Vinculación por QR (RF-16)

Página `/whatsapp/qr`, accesible **solo al Jefe de Calidad** (la ruta usa `auth` + `jefatura` y el controlador vuelve a validar `esCalidad()`).

```
Navegador (Calidad)
   │ GET /whatsapp/qr/estado  (Alpine, cada 30 s)
   ▼
WhatsAppController::estado()
   │ apikey leída desde config, nunca enviada al navegador
   ▼
Evolution API: GET /instance/connect/famep-wa
   ├─ state: open → "WhatsApp vinculado" (sin QR)
   ├─ base64/qrcode → imagen data:image/png;base64,...
   └─ error/timeout → mensaje en español con botón de reintento
```

- El QR **no se guarda en disco**: se obtiene en cada consulta porque expira en segundos.
- La API key vive en `.env.docker` (`AUTHENTICATION_API_KEY`) y solo se usa dentro del contenedor `app`.
- La URL interna usa el nombre de servicio `evolution-api:8080` (`EVOLUTION_API_URL`), no el puerto publicado.
- La interfaz usa Tailwind y Alpine locales, `aria-live`, `role="alert"`, foco visible y textos en español.
- Si la instancia se desvincula, la misma página vuelve a mostrar el QR automáticamente.

---

## 8. Rutas y permisos

### 8.1 Rutas públicas

| Método | URI | Nombre | Acción |
|---|---|---|---|
| GET | `/` | — | Redirige a login |
| GET | `/login` | `login` | Formulario de login |
| POST | `/login` | — | Autenticar |
| GET | `/consulta` | `consulta.acceso` | Formulario de código |
| POST | `/consulta` | `consulta.buscar` | Buscar código |
| GET | `/consulta/{codigo}` | `consulta.ver` | Obras del cliente |
| GET | `/consulta/{codigo}/obra/{obraId}` | `consulta.obra` | Detalle de obra |

### 8.2 Rutas autenticadas

| Método | URI | Nombre | Acción |
|---|---|---|---|
| POST | `/logout` | `logout` | Cerrar sesión |
| GET | `/perfil` | `perfil.edit` | Formulario de contraseña |
| PUT | `/perfil/password` | `perfil.password` | Cambiar contraseña |

### 8.3 Rutas del panel (`auth` + `jefatura`)

| Método | URI | Nombre | Acción |
|---|---|---|---|
| GET | `/dashboard` | `dashboard` | Panel principal |
| GET | `/obras` | `obras.index` | Listado |
| GET | `/obras/crear` | `obras.create` | Alta |
| POST | `/obras` | `obras.store` | Guardar |
| GET | `/obras/{id}/editar` | `obras.edit` | Edición |
| PUT | `/obras/{id}` | `obras.update` | Actualizar |
| DELETE | `/obras/{id}` | `obras.destroy` | Eliminar |
| GET | `/obras/{id}` | `obras.show` | Detalle |
| POST | `/obras/{obraId}/piezas` | `piezas.store` | Alta de pieza |
| GET | `/piezas/{id}/editar` | `piezas.edit` | Edición |
| PUT | `/piezas/{id}` | `piezas.update` | Actualizar |
| DELETE | `/piezas/{id}` | `piezas.destroy` | Eliminar |
| GET | `/piezas/{piezaId}` | `piezas.show` | Fases y dictámenes |
| POST | `/piezas/{piezaId}/fases` | `fases.store` | Registrar fase (con orden secuencial) |
| GET | `/fases/{fasePiezaId}/editar` | `fases.edit` | Formulario para cambiar operario |
| PUT | `/fases/{fasePiezaId}` | `fases.update` | Actualizar operario asignado |
| DELETE | `/fases/{fasePiezaId}` | `fases.destroy` | Eliminar asignación sin historial |
| POST | `/fases/{fasePiezaId}/calidad` | `calidad.store` | Dictaminar |
| GET | `/calidad/{id}/editar` | `calidad.edit` | Editar dictamen |
| PUT | `/calidad/{id}` | `calidad.update` | Actualizar dictamen |
| DELETE | `/calidad/{id}` | `calidad.destroy` | Eliminar dictamen |
| POST | `/fases/{fasePiezaId}/evidencia` | `evidencia.store` | Adjuntar foto |
| GET | `/api/notificaciones-calidad` | `api.notificaciones` | JSON por rol: dictámenes para Producción, actividad del taller para Calidad |
| GET | `/clientes` | `clientes.index` | Listado de clientes |
| GET | `/clientes/crear` | `clientes.create` | Alta de cliente |
| POST | `/clientes` | `clientes.store` | Guardar cliente |
| GET | `/reportes` | `reportes.index` | Índice de reportes |
| GET | `/reportes/avance-obra` | `reportes.avance-obra` | Avance por obra |
| GET | `/reportes/calidad` | `reportes.calidad` | Dictámenes |
| GET | `/reportes/productividad` | `reportes.productividad` | Productividad |
| GET | `/reportes/hallazgos` | `reportes.hallazgos` | Hallazgos |
| GET | `/reportes/historial` | `reportes.historial` | Historial de marca |
| GET | `/reportes/auditoria` | `reportes.auditoria` | Auditoría |
| GET | `/operarios` | `operarios.index` | Listado |
| GET | `/operarios/crear` | `operarios.create` | Alta |
| POST | `/operarios` | `operarios.store` | Guardar |
| GET | `/operarios/{id}/editar` | `operarios.edit` | Edición |
| PUT | `/operarios/{id}` | `operarios.update` | Actualizar |
| DELETE | `/operarios/{id}` | `operarios.destroy` | Eliminar |
| GET | `/whatsapp/qr` | `whatsapp.qr` | Página de vinculación (solo Calidad) |
| GET | `/whatsapp/qr/estado` | `whatsapp.qr.estado` | Estado/QR de la instancia (JSON, solo Calidad) |

### 8.4 Matriz de permisos

| Acción | Producción | Calidad | Cliente |
|---|---|---|---|
| Login | Sí | Sí | No |
| Dashboard | Sí | Sí | No |
| Obras: ver | Sí | Sí | No |
| Obras: crear/editar/eliminar | Sí | No | No |
| Piezas: crear/editar/eliminar | Sí | No | No |
| Fases: registrar y asignar | Sí | No | No |
| Fases: editar/eliminar asignación | Sí | No | No |
| Dictámenes: crear/editar/eliminar | No | Sí | No |
| Evidencias: adjuntar | No | Sí | No |
| Operarios: gestionar | Sí | No | No |
| Reportes | Sí | Sí | No |
| Auditoría | Sí | No | No |
| Clientes: gestionar | Sí | Sí | No |
| Vincular WhatsApp por QR | No | Sí | No |
| Portal de consulta | No | No | Sí |

---

## 9. Interfaz y pantallas

### 9.1 Inventario de vistas

| Grupo | Vistas |
|---|---|
| Autenticación | `auth/login` |
| Dashboard | `dashboard` |
| Obras | `obras/index`, `obras/create`, `obras/edit`, `obras/show` |
| Piezas | `piezas/show`, `piezas/edit`, `piezas/dictamen_edit`, `piezas/fases/edit` |
| Parciales de pieza | `piezas/partials/evidencias`, `fecha_fase`, `observaciones` |
| Operarios | `operarios/index`, `operarios/create`, `operarios/edit` |
| Clientes | `clientes/index`, `clientes/create`, `clientes/edit` |
| WhatsApp | `whatsapp/qr` (solo Calidad) |
| Perfil | `perfil/edit` |
| Portal | `portal/acceso`, `portal/index`, `portal/show` |
| Reportes | `reportes/index` y 6 reportes |
| Parciales de reportes | filtros, gráfica, icono |
| Componentes | sidebar, notificaciones (Producción), actividad-taller (Calidad), confirmar-eliminacion, toggle |
| Otros | `welcome` (plantilla Laravel, sin uso funcional) |

### 9.2 Convenciones de interfaz

- Tailwind local, sin CDN.
- Alpine.js para interactividad.
- Chart.js para gráficas.
- Foco visible con `focus-visible:ring-2 focus-visible:ring-blue-500`.
- Contraste AA.
- `aria-*`, `role="alert"` y semántica HTML.
- Etiquetas asociadas a inputs.
- Botón “Actualizar historial” en `piezas/show`, con recarga completa y estado ocupado.
- Imagen local en el login (`public/images/logo-famep.png`), con `alt` y dimensiones declaradas.
- Mensajes de error y éxito visibles.
- Diseño responsive.

---

## 10. Seguridad

| Medida | Implementación |
|---|---|
| Contraseñas | Bcrypt 12 rondas sobre `password_hash` |
| Autenticación | Guard `web` con sesión en base de datos |
| Autorización | Validaciones por rol en controladores |
| Aislamiento de clientes | `EnsureJefatura` redirige al portal |
| Portal | Código `FAMEP-XXXX` sin sesión |
| CSRF | Middleware estándar de Laravel |
| Inmutabilidad | Dictámenes de fases completadas en solo lectura |
| Auditoría | Escrituras con valores previos/nuevos; sin contraseñas |
| Archivos ocultos | Nginx los deniega |
| Archivos sensibles | `.env` y `.env.docker` en `.gitignore` |
| Subidas | Validación de tipo y tamaño; Nginx limita a 12 MB |
| Vinculación QR | Solo Calidad; la API key se usa en el servidor y nunca se envía al navegador |

**Estado de seguridad (20-nov-2026):** el password de PostgreSQL y la API key de Evolution ya no están en texto plano en `docker-compose.yml`; se leen desde `.env.docker` mediante `env_file` en los servicios `db` y `evolution-api`. Se creó `.env.docker.example` como plantilla sin valores reales. El traslado conservó los valores existentes: una rotación requiere un procedimiento aparte (PostgreSQL solo aplica la contraseña al inicializar `pgdata`).

---

## 11. Pruebas

### 11.1 Enfoque

El proyecto usa un **harness propio** en `tests/harness/`:

- Ejecuta contra la base PostgreSQL real.
- Abre una transacción y hace `rollback` al final.
- Aplica `Http::fake()` global: no dispara WhatsApp real.
- Comparte utilidades en `bootstrap_test.php`.
- Usa `operarioDePrueba()` para no depender de operarios reales.

### 11.2 Ejecución

```bash
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/<suite>.php
```

### 11.3 Suites y resultados al 22-nov-2026

| Suite | Checks | Resultado |
|---|---:|---|
| `flujo` | 15 | 15 OK |
| `estatus_obra` | 23 | 23 OK |
| `vistas` | 42 | 42 OK |
| `portal` | 67 | 67 OK |
| `obras_gestion` | 45 | 45 OK |
| `operarios_gestion` | 54 | 54 OK |
| `piezas_gestion` | 46 | 46 OK |
| `dictamenes_gestion` | 88 | 88 OK |
| `perfil_gestion` | 25 | 25 OK |
| `reportes_gestion` | 80 | 80 OK |
| `auditoria_gestion` | 40 | 40 OK |
| `zona_horaria` | 8 | 8 OK |
| `whatsapp_flujo` | 23 | 23 OK |
| `asignacion_operarios` | 27 | 27 OK |
| `whatsapp_qr` | 25 | 25 OK |
| `fases_orden_gestion` | 30 | 30 OK |
| `notificaciones_calidad` | 47 | 47 OK |
| **Total** | **685** | **685 OK, 0 FAIL** |

**Sin fallos conocidos al 22-nov-2026.** Los 7 fallos anteriores dependían de datos de demostración; tras el saneamiento y la semilla de la opción B las suites se reanclaron a la obra real `OB-2026-001`.

Baseline sembrado para las pruebas (obra `OB-2026-001`, pieza `TB1`):

| Tabla | Filas |
|---|---:|
| `obras` | 1 |
| `piezas` | 1 |
| `fases_pieza` | 3 |
| `observaciones_calidad` | 1 |
| `operarios` | 1 |
| `operario_fase` | 3 |
| `usuarios` | 3 |
| `sessions` | 0 |

Este es el estado sembrado, no un inventario en vivo: las capturas manuales posteriores desde la UI (por ejemplo, `OB-2026-002` / `C1` y operarios `OP-002` en adelante) aumentan los conteos y **no se borran sin autorización**. Las secuencias se realinean con `setval` contra el máximo existente tras cada corrida, porque los `rollback` del harness no revierten sus incrementos.

### 11.4 Cobertura funcional

| Área | Suite |
|---|---|
| Flujo completo de obra, pieza, fase y dictamen | `flujo` |
| Estatus derivados de obra | `estatus_obra` |
| Render de vistas y formularios | `vistas` |
| Portal público y aislamiento de clientes | `portal` |
| CRUD y reglas de obras | `obras_gestion` |
| CRUD y reglas de piezas | `piezas_gestion` |
| Dictámenes, inmutabilidad y notificaciones | `dictamenes_gestion` |
| Cambio de contraseña | `perfil_gestion` |
| Reportes, filtros y CSV | `reportes_gestion` |
| Operarios, habilitaciones y bloqueos | `operarios_gestion` |
| Auditoría | `auditoria_gestion` |
| Zona horaria | `zona_horaria` |
| Flujo de WhatsApp | `whatsapp_flujo` |
| Vinculación QR y permisos de Calidad | `whatsapp_qr` |
| Asignación y ocupación de operarios | `asignacion_operarios` |
| Orden secuencial y gestión de asignaciones | `fases_orden_gestion` |

---

## 12. Despliegue y operación

### 12.1 Servicios Docker

| Servicio | Imagen | Puerto host | Perfil |
|---|---|---|---|
| `app` | `famep-app:dev` | — | Todos |
| `web` | `nginx:alpine` | 9090 | Todos |
| `db` | `postgres:18-alpine` | 5433 | Todos |
| `adminer` | `adminer:latest` | 9092 | Solo dev |
| `n8n` | `docker.n8n.io/n8nio/n8n` | 5678 | Todos |
| `evolution-api` | `evoapicloud/evolution-api:v1.8.2` | 9091 | Todos |

### 12.2 Volúmenes

- `pgdata`: base de datos.
- `vendor`: dependencias en dev.
- `composer-cache`: caché de Composer.
- `n8n_data`: workflows y credenciales de n8n.
- `evolution_instances`: sesión de WhatsApp.

### 12.3 Entornos

| Aspecto | Dev | Prod |
|---|---|---|
| Código | Bind-mount | Dentro de la imagen |
| Dependencias | Con `--dev` | `--no-dev` |
| Storage | Del proyecto | Bind-mount `./storage` |
| Adminer | Disponible | Deshabilitado |
| Puerto DB | Expuesto | No expuesto |
| Debug | `true` | `false` |
| Healthcheck | — | PHP-FPM `127.0.0.1:9000` |

### 12.4 URLs

| Servicio | URL |
|---|---|
| Aplicación | `http://localhost:9090` |
| Red local | `http://192.168.100.118:9090` |
| n8n | `http://localhost:5678` |
| Evolution API | `http://localhost:9091` |
| Adminer | `http://localhost:9092` |
| PostgreSQL | `localhost:5433` |

### 12.5 Comandos

```bash
# Levantar desarrollo
docker compose up -d

# Limpiar cachés
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan route:clear
docker compose exec -T app php artisan config:clear

# Ejecutar una suite
docker compose exec -T app php tests/harness/bootstrap_test.php tests/harness/<suite>.php

# Producción
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

### 12.6 Variables de entorno principales

| Variable | Uso |
|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL` | Configuración Laravel |
| `APP_DISPLAY_TIMEZONE` | Zona de presentación |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL (Laravel) |
| `POSTGRES_PASSWORD` | PostgreSQL (contenedor `db`); debe coincidir con `DB_PASSWORD` |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Persistencia |
| `FILESYSTEM_DISK` | Archivos |
| `N8N_WEBHOOK_WHATSAPP_URL` | Webhook de n8n |
| `N8N_WEBHOOK_TIMEOUT` | Timeout del webhook |
| `WHATSAPP_NOTIFY_PHONE` | Teléfono destino |
| `AUTHENTICATION_API_KEY` | API key de Evolution (contenedor y Laravel) |
| `EVOLUTION_API_URL` | URL interna de Evolution (`http://evolution-api:8080`) |
| `EVOLUTION_INSTANCE` | Instancia de WhatsApp (`famep-wa`) |
| `EVOLUTION_QR_TIMEOUT` | Timeout de la consulta del QR (10 s) |

> Los valores reales viven en `.env.docker` (excluido de Git) y no deben reproducirse en documentación ni en repositorios. Use `.env.docker.example` como plantilla.

### 12.7 Operación recomendada

**Respaldos:**

- `pg_dump` de `famep_db`.
- Respaldo de `storage/app/public` (evidencias).
- Respaldo del volumen de n8n y de la sesión de Evolution.

**Monitoreo:**

- Logs de Laravel en `storage/logs`.
- Estado de contenedores con `docker compose ps`.
- Healthcheck de producción.
- Ejecuciones de n8n.

**Mantenimiento:**

- Limpiar cachés tras desplegar.
- Reconstruir la imagen de producción para aplicar cambios.
- Revisar logs de alertas fallidas.

---

## 13. Mantenimiento y convenciones

### 13.1 Reglas para cambios

1. Leer la documentación antes de cambios grandes.
2. Respetar UTC para almacenamiento y `FechaLocal` para presentación.
3. Usar el dump como fuente de verdad del esquema.
4. No crear migraciones de dominio.
5. No introducir CDN ni Vite en runtime.
6. No reescribir fechas históricas.
7. No registrar `password_hash` en auditoría.
8. No borrar los archivos ajenos de la raíz (`email.'`, `nombre.PHP_EOL))`, `CLAUDE.md`) sin autorización.
9. Mantener los assets locales.
10. Mantener la interfaz y los mensajes en español.
11. Ejecutar la suite correspondiente después de cada cambio.
12. Pedir autorización antes de cambiar reglas de negocio.

### 13.2 Convenciones de código

- PSR-4 y estilo Laravel.
- Controladores con validaciones y mensajes en español.
- Modelos con relaciones explícitas y nombres de tabla.
- Cálculos derivados centralizados en modelos.
- Reglas de negocio en controladores, no en observers, para no bloquear fixtures ni importaciones.
- Transacciones con `lockForUpdate` en operaciones concurrentes.
- Vistas Blade con Tailwind local y accesibilidad.

### 13.3 Archivos que no forman parte del runtime

| Archivo | Observación |
|---|---|
| `email.'` | Archivo ajeno; no borrar sin autorización |
| `nombre.PHP_EOL))` | Archivo ajeno; no borrar sin autorización |
| `CLAUDE.md` | Instrucciones de agentes; no borrar sin autorización |
| `AGENTS.md` | Instrucciones de agentes; plantilla Laravel Boost |

### 13.4 Trazabilidad de cambios recientes

| Regla | Archivos |
|---|---|
| A5 / 2d: operario con una sola tarea activa | `Operario.php`, `FasePiezaController.php` |
| A6: solo operarios activos | `FasePiezaController.php` |
| A8: no quitar habilitación en uso | `OperarioController.php` |
| A9: no desactivar operario ocupado | `OperarioController.php` |
| Reactivación de fase | `ObservacionCalidadController.php`, `FasePieza.php` |
| B4: no reasignar obras de otro cliente | `ClienteController.php`, `clientes/index.blade.php` |
| RN-22/RN-23: activar/desactivar y eliminar cliente | `ClienteController.php`, `routes/web.php`, `clientes/index.blade.php`, `clientes/edit.blade.php`, `tests/harness/portal.php` |
| RN-24: compromiso posterior al inicio | `ObraController.php`, `obras/create.blade.php`, `obras/edit.blade.php`, `tests/harness/obras_gestion.php` |
| RN-25: cantidad fija en 1 al agregar pieza | `PiezaController.php`, `obras/show.blade.php`, `tests/harness/piezas_gestion.php` |
| RN-26: paneles de notificaciones por rol | `DashboardController.php`, `dashboard.blade.php`, `components/dropdown-notificaciones.blade.php`, `components/dropdown-actividad-taller.blade.php`, `tests/harness/notificaciones_calidad.php` |
| RN-27: errores visibles en detalle de obra | `obras/show.blade.php`, `tests/harness/piezas_gestion.php` |
| RN-28: confirmación propia en todas las eliminaciones | `components/confirmar-eliminacion.blade.php`, `obras/index.blade.php`, `obras/show.blade.php`, `operarios/index.blade.php`, `clientes/index.blade.php`, `piezas/show.blade.php`, `piezas/partials/observaciones.blade.php`, `tests/harness/obras_gestion.php`, `tests/harness/operarios_gestion.php`, `tests/harness/piezas_gestion.php`, `tests/harness/portal.php`, `tests/harness/vistas.php` |
| B5: cliente de obra debe ser rol 3 | `ObraController.php` |
| C3: marca única por obra | `PiezaController.php` |
| D9: evidencia en fase válida y abierta | `EvidenciaController.php` |
| RN-18: orden Armado → Soldadura → Pintura | `FasePiezaController.php`, `tests/harness/fases_orden_gestion.php` |
| RN-19/RN-20: editar/eliminar asignaciones | `FasePiezaController.php`, `routes/web.php`, `piezas/fases/edit.blade.php`, `piezas/show.blade.php` |
| RN-21: cerrar conservando el operario | `FasePiezaController.php`, `public/js/fases-evaluacion.js`, `tests/harness/fases_orden_gestion.php` |
| Botón de refresh del historial | `resources/views/piezas/show.blade.php`, `tests/harness/vistas.php` |
| Suite de asignación | `tests/harness/asignacion_operarios.php` |

---

## 14. Riesgos, pendientes y deuda técnica

### 14.1 Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Documentación desactualizada | Decisiones erróneas | Actualizar al cierre de cada fase |
| Datos de prueba en BD real | Reportes y bloqueos confusos | Limpiar datos demo y `OP-001` |
| Sin migraciones de dominio | Cambios de esquema frágiles | Documentar y versionar el dump |
| Rotación de credenciales | Si se cambian en `.env.docker`, PostgreSQL no actualiza `pgdata` | Rotar con procedimiento dedicado; por ahora solo se movieron los valores |
| Sin worker de colas | `dispatchSync` bloquea la petición | Documentar o habilitar worker |
| WhatsApp sin contrato observable | Fallos silenciosos | Nodo `Respond to Webhook` e idempotencia |
| `APP_LOCALE=en` | Mensajes estándar en inglés | Agregar `lang/es` o documentar |
| Sin runbook de respaldos | Pérdida de evidencias o BD | Documentar `pg_dump` y respaldo de storage |
| Regla de operario único | Bloquea asignaciones si hay datos sucios | Documentar cierre/reasignación |

### 14.2 Pendientes

1. Actualizar conteos de pruebas en README (la documentación maestro ya quedó al 22-nov-2026).
2. Documentar formalmente las reglas A5/A6/A8/A9/B4/B5/C3/D9.
3. Corregir la afirmación “no es repositorio Git”.
4. Crear diccionario de datos y ERD.
5. Crear runbook de respaldo, restauración y rollback.
6. Exportar el workflow de n8n al repositorio.
7. Decidir e implementar E7 (restringir altas de obras/piezas a Producción).
8. Rotar credenciales con procedimiento aparte (PostgreSQL aplica `POSTGRES_PASSWORD` solo al inicializar `pgdata`).
9. Agregar `lang/es` o definir idioma oficial de mensajes.

### 14.3 Deuda técnica

- `EvidenciaController@store` envía `fecha_subida`, campo inexistente en la tabla.
- No hay worker de colas.
- No hay migraciones de dominio.
- No hay contrato de respuesta del webhook de n8n.
- No hay idempotencia en el payload de WhatsApp.
- No hay pruebas automatizadas de accesibilidad.
- No hay pipeline CI/CD.
- No hay ambiente de staging documentado.

---

## 15. Glosario

| Término | Definición |
|---|---|
| Obra | Orden de trabajo de estructuras metálicas |
| Pieza / Marca | Elemento fabricado dentro de una obra |
| Fase | Etapa productiva: Armado, Soldadura o Pintura |
| Dictamen | Resultado de la inspección de Calidad |
| Reproceso | Corrección solicitada por Calidad |
| Evidencia | Fotografía que respalda un dictamen o fase |
| Operario | Personal de taller que ejecuta una fase |
| Habilitación | Fase que un operario puede ejecutar |
| Jefatura | Usuario con rol Producción o Calidad |
| Portal | Consulta pública del cliente con código |
| Auditoría | Bitácora de escrituras del sistema |
| Harness | Arnés de pruebas propio del proyecto |
| Rollback | Reversión de la transacción de prueba |
| RF-33 | Requerimiento de alertas WhatsApp |

---

## 16. Anexos

### A. Archivos clave

| Ruta | Contenido |
|---|---|
| `routes/web.php` | Todas las rutas |
| `app/Http/Controllers/FasePiezaController.php` | Asignación, orden secuencial, edición/eliminación y cierre |
| `app/Models/Operario.php` | `ESTADOS_QUE_OCUPAN`, `faseActiva()` |
| `app/Models/FasePieza.php` | Estados derivados e inmutabilidad |
| `app/Http/Controllers/ObservacionCalidadController.php` | Dictámenes, reactivación y WhatsApp |
| `app/Http/Controllers/OperarioController.php` | Bloqueos A8/A9 |
| `app/Http/Controllers/ClienteController.php` | Portal, B4, RN-22 y RN-23 |
| `resources/views/clientes/edit.blade.php` | Formulario para activar/desactivar el código de acceso |
| `app/Http/Controllers/PiezaController.php` | C3 y edición con avance |
| `app/Http/Controllers/EvidenciaController.php` | D9 |
| `app/Http/Controllers/ObraController.php` | B5 y CRUD de obras |
| `app/Http/Controllers/ReporteController.php` | Reportes, filtros y CSV |
| `app/Services/AuditoriaService.php` | Auditoría de escrituras |
| `app/Support/FechaLocal.php` | Conversión UTC ↔ local |
| `app/Jobs/SendWhatsAppAlertJob.php` | Envío a n8n |
| `app/Http/Controllers/WhatsAppController.php` | Consulta del QR a Evolution (solo Calidad) |
| `config/services.php` | Configuración `n8n.*` y `evolution.*` |
| `resources/views/auth/login.blade.php` | Login con logo local sobre el título |
| `public/images/logo-famep.png` | Imagen del login (1920×720, 41 KB) |
| `resources/views/whatsapp/qr.blade.php` | Pantalla de vinculación con auto-refresh |
| `resources/views/piezas/show.blade.php` | Panel de fases, historial y botón de actualización |
| `resources/views/piezas/fases/edit.blade.php` | Formulario para cambiar el operario de una fase |
| `.env.docker.example` | Plantilla de secretos sin valores reales |
| `docker/postgres/init/01-inicial.sql` | Esquema y datos iniciales |
| `docker/web/nginx.conf` | Alias de evidencias y límites |
| `docker-compose.yml` / `.prod.yml` | Infraestructura dev/prod |
| `tests/harness/` | 17 suites + bootstrap |
| `docs/` | Documentación técnica existente |

### B. Documentos existentes y estado

| Documento | Estado |
|---|---|
| `README.md` | La plantilla `.env.docker.example` ya existe; conteos de pruebas siguen desactualizados |
| `docs/COMPOSICION-APP.md` | Completo; datos de pruebas y Git obsoletos |
| `docs/CONTEXTO-PARA-IA.md` | Útil; anterior a las reglas nuevas |
| `docs/REPORTE-ALERTAS-WHATSAPP-FLUJO.md` | Vigente |
| `docs/REPORTE-QR-WHATSAPP.md` | Vigente |
| `AGENTS.md` / `CLAUDE.md` | Plantilla genérica de Laravel Boost |

### C. Estado del repositorio

| Aspecto | Valor |
|---|---|
| Rama | `dev` |
| Commits | 8 |
| Archivos modificados sin commitear | 19 |
| Sin seguimiento | `.agents/`, `backups/`, `skills-lock.json`, `skills/`, `resources/views/components/confirmar-eliminacion.blade.php`, `resources/views/components/dropdown-actividad-taller.blade.php`, `tests/harness/notificaciones_calidad.php` |

### D. Resultado de la última verificación

```text
17 suites
685 checks
685 OK
0 FAIL
Rollback correcto en todas las suites
fases_orden_gestion: 30 OK, 0 FAIL
notificaciones_calidad: 47 OK, 0 FAIL
obras_gestion: 45 OK, 0 FAIL
operarios_gestion: 54 OK, 0 FAIL
piezas_gestion: 46 OK, 0 FAIL
vistas: 42 OK, 0 FAIL
portal: 67 OK, 0 FAIL
whatsapp_qr: 25 OK, 0 FAIL
BD sembrada: 1 obra, 1 pieza, 3 fases, 1 observación, 1 operario, 3 habilitaciones, 3 usuarios, 0 sesiones
Capturas manuales posteriores pueden aumentar esos conteos (no se borran sin autorización)
Secuencias realineadas contra el máximo existente tras la última corrida
```

---

**Fin del documento.**

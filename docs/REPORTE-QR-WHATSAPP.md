# Reporte: QR de WhatsApp y cambio de puerto

**Fecha:** 28-oct-2026
**Alcance:** verificar si el cambio de puertos (8080/8081 → 9091 para Evolution API) dejó desactualizado el QR o algún punto de la cadena de WhatsApp.
**Tipo:** diagnóstico de solo lectura. No se modificó ningún archivo ni configuración.

---

## 1. Resumen ejecutivo

- **No hace falta regenerar el QR.** La instancia `famep-wa` sigue vinculada y en estado `open` después de recrear los contenedores.
- **El QR no contiene ni depende del puerto.** Es un token temporal de vinculación de WhatsApp; el puerto solo define la URL donde se solicita.
- **El archivo `qr.html` sí está obsoleto:** contiene una imagen QR fija del **9-oct-2026**, ya expirada, y además no se sirve desde la aplicación (vive en la raíz, fuera de `public/`).
- **Los puertos están bien configurados y son consistentes** entre host, red interna, n8n y Laravel.
- Se detectaron **dos detalles menores** que no rompen el envío actual pero conviene corregir (ver sección 6).

---

## 2. Contexto del cambio

| Antes | Ahora | Motivo |
|---|---|---|
| 8080 / 8081 / 8082 | **9090** web, **9091** Evolution, **9092** Adminer | Windows reservó los rangos 8080–8082 (Hyper-V/WinNAT) |

Evolution API sigue escuchando en **8080 dentro del contenedor**; el cambio fue solo en el puerto publicado al host.

---

## 3. Verificación de la cadena

| Punto | Valor actual | Estado |
|---|---|---|
| Puerto publicado de Evolution | `9091 → 8080/tcp` | Correcto |
| `SERVER_URL` de Evolution | `http://localhost:9091` | Correcto para acceso local |
| API de Evolution | `http://localhost:9091/` → `200`, v1.8.2 | Correcto |
| Manager | `http://localhost:9091/manager` → `200` | Correcto |
| Manager por LAN | `http://192.168.100.118:9091/manager` → `200` | Correcto |
| Instancia | `famep-wa`, `state: "open"` | Conectada |
| Dueño vinculado | `5217731169349@s.whatsapp.net` | Correcto |
| Almacenamiento de la sesión | volumen `famep_evolution_instances` → `/evolution/instances` | Persistente |
| n8n → Evolution | `http://famep-evolution:8080/message/sendText/famep-wa` | Correcto (puerto interno) |
| Laravel → n8n | `http://famep-n8n:5678/webhook/famep-alerta-calidad` | Correcto |
| Puertos en `.env.docker` | Sin URLs de Evolution; solo n8n y teléfono | Sin residuos del puerto viejo |

**Búsqueda de puertos antiguos:** no quedan referencias a `8081` ni `8082` en configuración. Las únicas apariciones de `8080` son el puerto interno del contenedor y el mapeo de Adminer (`9092:8080`), ambos esperados.

---

## 4. Por qué el QR no dependía del puerto

El QR es un **token de vinculación que genera WhatsApp**, válido por pocos segundos y de un solo uso. No incluye la URL del servidor ni el puerto.

El puerto solo interviene en **dónde se consulta** el QR:

```text
Navegador → http://localhost:9091/manager → Evolution (8080 interno) → WhatsApp
```

Como la sesión de WhatsApp se guarda en el volumen `famep_evolution_instances`, al recrear los contenedores por el cambio de puerto la vinculación **se conservó**. El log de Evolution lo confirma:

```text
[famep-wa] CONNECTED TO WHATSAPP
[famep-wa] connectionStatus: open
```

Por eso `GET /instance/connect/famep-wa` responde hoy:

```json
{"instance":{"instanceName":"famep-wa","state":"open"}}
```

Es decir: **no devuelve QR porque ya está conectada**. Ese comportamiento es correcto, no un error.

---

## 5. Estado del archivo `qr.html`

| Dato | Valor |
|---|---|
| Ubicación | `C:\laragon\www\famep-app\qr.html` (raíz del proyecto) |
| Última modificación | **9-oct-2026 10:10 a.m.** (19 días antes de este reporte) |
| Contenido | Imagen QR embebida en base64, fija |
| Puerto referenciado | Ninguno (el QR no lleva puerto) |
| Servido por Nginx | **No** — el docroot es `/var/www/html/public` |
| Utilidad actual | **Nula**: el QR ya expiró |

**Conclusión:** el archivo es un residuo de la vinculación del 9 de octubre, de la etapa previa al cambio de puertos. No es la causa de ningún fallo actual y no debe usarse para vincular.

---

## 6. Inconsistencias detectadas (menores)

### 6.1 `SERVER_URL` con `localhost`

```yaml
SERVER_URL: http://localhost:9091
```

- Funciona si el manager se abre **desde este equipo**.
- Si se abre desde otro dispositivo, la interfaz carga, pero la API reporta `serverUrl: http://localhost:9091`, que en ese dispositivo apunta a sí mismo.
- **Impacto actual:** bajo (el envío no usa `SERVER_URL`).
- **Recomendación:** si se va a administrar desde otro equipo o celular, usar `http://192.168.100.118:9091` o dejar el valor en una variable de entorno.

### 6.2 Webhook de instancia apuntando a `localhost`

`fetchInstances` reporta:

```json
"integration": { "webhook_wa_business": "http://localhost:9091/webhook/whatsapp/famep-wa" }
```

- Dentro del contenedor, `localhost:9091` **no existe** (escucha en 8080).
- Es un webhook de la integración *WhatsApp Business*, **no se usa** con Baileys; el envío real lo hace n8n por HTTP directo.
- Además, `DATABASE_ENABLED=false` hace que `/webhook/find/famep-wa` y `/settings/find/famep-wa` devuelvan `{}`: esas configuraciones no se persisten.
- **Impacto actual:** nulo en el flujo de alertas.
- **Recomendación:** no configurar webhooks por instancia; si se necesita, usar `http://famep-evolution:8080` (interno) o la IP LAN (externo).

---

## 7. Procedimiento correcto para regenerar el QR (solo si se desconecta)

> Desde el 20-nov-2026 la API key ya no se fija en `docker-compose.yml`: se lee desde `.env.docker` (`AUTHENTICATION_API_KEY`). En los comandos siguientes se usa esa variable como placeholder; no pegue el valor real en la documentación.

**Caso A — la instancia sigue existiendo pero está desconectada:**

1. Abrir `http://localhost:9091/manager`.
2. Entrar con la API key `$AUTHENTICATION_API_KEY`.
3. Elegir la instancia `famep-wa` → **Connect / QR**.
4. Escanear desde el celular: *WhatsApp → Ajustes → Dispositivos vinculados → Vincular un dispositivo*.
5. El QR se renueva solo cada ~30 s; no guardar la imagen.

**Caso B — desde consola:**

```bash
curl -H "apikey: $AUTHENTICATION_API_KEY" \
     http://localhost:9091/instance/connect/famep-wa
```

Devuelve `base64` del QR si está desconectada, o `{"state":"open"}` si ya está vinculada.

**Caso C — la instancia se perdió por completo (no es el caso actual):**

```bash
curl -X DELETE -H "apikey: $AUTHENTICATION_API_KEY" \
     http://localhost:9091/instance/logout/famep-wa

curl -X POST -H "apikey: $AUTHENTICATION_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"instanceName":"famep-wa","qrcode":true,"integration":"WHATSAPP-BAILEYS"}' \
     http://localhost:9091/instance/create

curl -H "apikey: $AUTHENTICATION_API_KEY" \
     http://localhost:9091/instance/connect/famep-wa
```

> El volumen `famep_evolution_instances` evita tener que repetir esto en cada `docker compose up -d`.

---

## 8. Recomendaciones

| Prioridad | Acción |
|---|---|
| Media | Eliminar `qr.html` de la raíz (es un QR expirado y no se sirve). Si se quiere una página de vinculación, generarla dinámicamente contra `/instance/connect/famep-wa`. |
| Baja | Documentar en `docs/COMPOSICION-APP.md` que el QR se obtiene en `http://localhost:9091/manager` y que no depende del puerto. |
| Baja | Decidir si `SERVER_URL` debe ser `localhost` o la IP LAN según dónde se administre. |
| Baja | No usar `webhook_wa_business` con `localhost`; no es necesario para el flujo actual. |

---

## 9. Evidencia recolectada

```text
Puertos:            9091 -> 8080/tcp
SERVER_URL:         http://localhost:9091
Instancia:          famep-wa | status: open | owner: 5217731169349@s.whatsapp.net
GET /instance/connect/famep-wa -> {"instance":{"instanceName":"famep-wa","state":"open"}}
GET /instance/fetchInstances     -> 200 (1 instancia)
GET /manager                     -> 200 (localhost y 192.168.100.118)
GET /                            -> 200 | v1.8.2
Volumen sesión:     famep_evolution_instances:/evolution/instances
qr.html:            modificado 09-oct-2026 10:10, QR base64 fijo, fuera de public/
Búsqueda 8081/8082: sin referencias en configuración del proyecto
```

---

## Conclusión

El cambio de puerto **no invalidó el QR ni la vinculación**. La instancia está conectada y el flujo puede enviar mensajes. El único elemento realmente desactualizado es el archivo `qr.html`, que contiene un QR vencido del 9 de octubre y no forma parte de la aplicación. Si en el futuro hace falta revincular, el QR correcto se obtiene en `http://localhost:9091/manager`.

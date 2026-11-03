# Reporte: alertas WhatsApp en dictámenes (RF-33)

**Fecha:** 28-oct-2026
**Alcance:** explicar por qué al **eliminar un dictamen** sí se envía WhatsApp y al **dictaminar** no se envía nada. Solo diagnóstico; no se modificó código.
**Componentes:** `ObservacionCalidadController`, `SendWhatsAppAlertJob`, workflow `My workflow` (n8n) y Evolution API.

---

## 1. Resumen ejecutivo

No hay un fallo en la cadena Laravel → n8n → Evolution: los envíos que salieron llegaron al webhook y n8n los procesó.

Lo que se observó tiene **dos causas distintas**, y solo una es un bug real:

| # | Observación del usuario | Qué pasó realmente | ¿Bug? |
|---|---|---|---|
| 1 | "Elimino un comentario y **sí** se va el mensaje" | `destroy()` reenvía una alerta usando el **último dictamen que queda**, no el que se borró. Es un dictamen viejo con **fecha de hoy**. | **Sí** |
| 2 | "Cuando dictamino **no** se envía nada" | El dictamen registrado fue **Aprobado**, y RF-33 solo notifica `Rechazado` / `Enviado a Reproceso`. | No (por diseño), pero mal percibido |

En corto: el mensaje que llega al borrar es una **alerta fantasma de un dictamen histórico**, y el dictamen nuevo no alertó porque era una aprobación.

---

## 2. Evidencia (cronología exacta)

Todo en UTC; la hora local es UTC−6.

| Hora (UTC) | Acción | Registro | ¿Alerta? |
|---|---|---|---|
| 22-oct 23:09:43 | Dictamen **Enviado a Reproceso** ("Prueba observación") | `observaciones_calidad` id **568** | Sí (en su momento) |
| 28-oct 20:33:18 | Dictamen **Aprobado** ("Prueba comentario") | id **673**, auditoría 694 | **No** (Aprobado) |
| 28-oct 20:57:23 | **Elimina** el dictamen 673 | auditoría 695 | **Sí** → ejecución n8n **133** |
| 28-oct 20:58:36 | Dictamen **Aprobado** ("Prueba 1") | id **674**, auditoría 696 | **No** → no existe ejecución 134 |

### Payload real de la ejecución n8n 133 (la que sí llegó)

```json
{
  "obra": "Nave Prueba",
  "marca": "C1",
  "fase": "Armado",
  "resultado": "Enviado a Reproceso",
  "inspector": "Ana Laura Torres",
  "observacion": "Prueba observación",
  "fecha": "28/10/2026 14:57",
  "telefono_destino": "5215562029735"
}
```

Ese dictamen (`Prueba observación`) es del **22-oct**, pero el mensaje dice **28/10/2026 14:57**. La alerta se generó al borrar un dictamen **Aprobado**, y describe un rechazo de **6 días antes** como si fuera de hoy. Ese es el mensaje que el usuario vio llegar.

### Estado de la fase 360

- 20:33 → `Aprobada por Calidad` (dictamen 673).
- 20:57 → al borrar 673, `recalcularEstadoDesdeDictamenes()` toma el último vigente (568, reproceso) y la fase **retrocede** a `En Reprosceso`.
- 20:58 → admite un nuevo dictamen y se registra 674 (`Aprobado`).
- Estado actual: `Aprobada por Calidad`.

---

## 3. Cómo funciona hoy el flujo

`app/Http/Controllers/ObservacionCalidadController.php`

| Método | Línea | Dispara WhatsApp | Criterio |
|---|---|---|---|
| `store()` | 106 | Sí | `resultado ∈ {Rechazado, Enviado a Reproceso}` |
| `update()` | 220 | Sí | mismo criterio, **sin comparar** contra el resultado anterior |
| `destroy()` | 269-280 | Sí | busca el **último dictamen restante** y reenvía la alerta con `FechaLocal::ahora()` |

`notificarWhatsApp()` (línea 290):

1. Filtra por resultado.
2. Normaliza el teléfono (`521` + 10 dígitos) y descarta placeholders.
3. Relee la fase y hace `SendWhatsAppAlertJob::dispatchSync([...])`.

`SendWhatsAppAlertJob` (línea 24): `Http::timeout(5)->post(...)`, envuelto en `try/catch` que solo registra excepciones.

---

## 4. Causas raíz

### R1 — `destroy()` renotifica un dictamen histórico (bug principal)

Al eliminar, el código no notifica "se eliminó un dictamen": toma el **último dictamen que queda** y lo manda como si acabara de ocurrir.

Consecuencias:

- **Alerta duplicada**: el dictamen 568 ya se había notificado el 22-oct.
- **Datos falsos**: `fecha` = ahora, `inspector` = usuario que borra, pero `resultado` y `observacion` son de otro dictamen y otro día.
- **Ruido operativo**: cada corrección de Calidad (borrar un dictamen) dispara un mensaje que Producción interpreta como un rechazo nuevo.
- Efecto colateral: borrar una aprobación **reabre la fase** silenciosamente (`En Reprosceso`), y acto seguido sale la alerta de reproceso.

### R2 — Los dictámenes "Aprobado" no notifican (por diseño, mal percibido)

`notificarWhatsApp()` retorna sin hacer nada si el resultado no es rechazo/reproceso. El dictamen 674 fue `Aprobado` y por eso no hay ejecución 134 en n8n. No es un fallo técnico: es la regla RF-33 actual.

El problema es de **expectativa**: la interfaz llama "Dictaminar" a las tres opciones y no avisa que solo dos generan alerta, así que un "Aprobado" silencioso se percibe como bug.

### R3 — `update()` renotifica aunque no cambie el resultado

`update()` llama a `notificarWhatsApp()` con el resultado **nuevo**, sin compararlo con el anterior. Editar solo la descripción de un dictamen en reproceso vuelve a mandar el mismo mensaje. Es la misma familia de bug que R1 (alertas que no corresponden a un evento nuevo).

### R4 — Los fallos de n8n/Evolution son silenciosos

`Http::post()` no lanza excepción con respuestas 4xx/5xx y el código no revisa `$response->failed()` ni el cuerpo. Si Evolution rechaza el envío (400) o n8n devuelve error, **no queda registro** y el usuario ve "Dictamen registrado" igual.

A esto se suma `timeout(5)`: n8n ejecuta el workflow completo (incluido el envío a Evolution) antes de responder. En el log del 19-oct hay dos `cURL error 28 ... timed out after 5001 ms`. n8n sí terminó el trabajo, pero Laravel registró un error y el usuario no supo si el mensaje salió.

### R5 — El envío es síncrono dentro de la petición web

`dispatchSync` bloquea la respuesta del formulario hasta que n8n contesta (o expira). Con WhatsApp lento, el usuario espera y puede recibir un 500 si algo no se captura.

---

## 5. Matriz de comportamiento

| Acción | Alerta hoy | Alerta esperada |
|---|---|---|
| Dictaminar Rechazado | Sí | Sí |
| Dictaminar Enviado a Reproceso | Sí | Sí |
| Dictaminar Aprobado | No | No (documentar en UI) |
| Editar descripción sin cambiar resultado | **Sí (duplicado)** | No |
| Cambiar Aprobado → Reproceso | Sí | Sí |
| Cambiar Reproceso → Aprobado | No | No |
| **Eliminar dictamen** | **Sí (histórico, con fecha falsa)** | **No** |
| Eliminar el último dictamen de la fase | No (no queda resultado) | No |

---

## 6. Solución propuesta

### Fase 1 — Quitar la alerta fantasma (obligatorio)

En `ObservacionCalidadController::destroy()`, eliminar el bloque de renotificación (líneas 269-280). Borrar un dictamen es una **corrección administrativa**, no un hallazgo nuevo.

```php
// destroy(): después de la transacción
return redirect()->route('piezas.show', $piezaId)
    ->with('success', 'Dictamen eliminado.');
```

Si se quiere dejar rastro, la vía correcta es la **auditoría** (ya existe: `logs_auditoria`) o un aviso explícito "Dictamen anulado", nunca reenviar el dictamen anterior.

### Fase 2 — Notificar solo cambios reales de resultado

En `update()`, capturar el resultado anterior y notificar únicamente si cambió **y** el nuevo es rechazo/reproceso:

```php
$resultadoAnterior = $dictamen->resultado;
// ... dentro de la transacción, tras el update ...
// fuera de la transacción:
if ($resultadoAnterior !== $validated['resultado']) {
    $this->notificarWhatsApp($dictamen->fase_pieza_id, $validated['resultado'], $validated['descripcion']);
}
```

### Fase 3 — Hacer observable el resultado del envío

En `SendWhatsAppAlertJob`:

```php
$response = Http::timeout(15)->acceptJson()->post(config('services.n8n.webhook_url'), $this->payload);

if ($response->failed()) {
    Log::error('n8n rechazó la alerta de WhatsApp', [
        'status' => $response->status(),
        'body'   => $response->body(),
        'payload' => $this->payload,
    ]);
}
```

Complementos recomendados:

- `timeout` configurable (`config('services.n8n.timeout', 15)`).
- Clave de idempotencia en el payload (p. ej. `dictamen_id`) para que n8n no duplique si se reintenta.
- Revisar en n8n que el nodo HTTP Request marque error si Evolution responde 4xx.

### Fase 4 — Aclarar la regla en la interfaz

En el formulario de dictamen (`resources/views/piezas/show.blade.php`, línea 201) agregar una nota:

> "Solo **Rechazar** y **Enviar a Reproceso** generan alerta por WhatsApp. **Aprobar** no notifica."

Opcional: confirmación explícita al elegir "Aprobado".

### Fase 5 — Pruebas de regresión

Nueva suite `tests/harness/whatsapp_flujo.php` con `Http::fake()`:

1. `store` con `Rechazado` → 1 request.
2. `store` con `Aprobado` → 0 requests.
3. `update` sin cambio de resultado → 0 requests.
4. `update` de `Aprobado` a `Reproceso` → 1 request.
5. `update` de `Reproceso` a `Aprobado` → 0 requests.
6. **`destroy` → 0 requests** (el caso que hoy falla).
7. Payload: `telefono_destino` normalizado, `fecha` en `America/Mexico_City`, sin `password_hash` ni datos sensibles.

Se sumaría a las 12 suites actuales (430 checks).

---

## 7. Verificación manual después del fix

1. Dictaminar **Rechazado** en una fase en proceso → debe llegar 1 mensaje y aparecer 1 ejecución en n8n.
2. **Eliminar** ese dictamen → **no** debe llegar mensaje ni crear ejecución.
3. Dictaminar **Aprobado** → no debe llegar mensaje (comportamiento esperado, ahora documentado).
4. Editar solo la descripción de un dictamen en reproceso → no debe llegar mensaje.
5. Cambiar un dictamen de Aprobado a Reproceso → debe llegar 1 mensaje.
6. Revisar `docker compose logs app` y `storage/logs/laravel.log`: sin `cURL error 28` nuevos.

---

## 8. Decisiones pendientes

1. **¿Los "Aprobado" deben notificar?** Recomendación: no (mantener RF-33), solo aclararlo en la UI.
2. **¿Eliminar un dictamen debe avisar?** Recomendación: no enviar WhatsApp; dejar solo auditoría.
3. **¿Se cambia `dispatchSync` por cola?** Recomendación: mantener síncrono en esta etapa, pero con timeout 15 s y log de respuesta. Si se pasa a cola, hace falta un worker (`QUEUE_CONNECTION=database` ya está configurado, pero no hay worker corriendo).

---

## 9. Archivos involucrados

- `app/Http/Controllers/ObservacionCalidadController.php` — `store()` 106, `update()` 220, `destroy()` 269-280, `notificarWhatsApp()` 290.
- `app/Jobs/SendWhatsAppAlertJob.php` — línea 24 (timeout y manejo de respuesta).
- `resources/views/piezas/show.blade.php` — formulario de dictamen (201-205).
- `tests/harness/dictamenes_gestion.php` — cubre store/update, **no cubre destroy**.
- `docs/COMPOSICION-APP.md` — sección 8 (RF-33).

---

## 10. Estado de implementación (28-oct-2026)

Aplicado y verificado:

| Cambio | Archivo |
|---|---|
| `destroy()` ya no reenvía el dictamen vigente | `app/Http/Controllers/ObservacionCalidadController.php` |
| `update()` notifica solo si cambia el resultado | `app/Http/Controllers/ObservacionCalidadController.php` |
| Timeout configurable (15 s) y log de respuestas 4xx/5xx | `app/Jobs/SendWhatsAppAlertJob.php`, `config/services.php` |
| Aviso en el formulario: Aprobar no genera alerta | `resources/views/piezas/show.blade.php` |
| Suite `whatsapp_flujo` (23 checks) | `tests/harness/whatsapp_flujo.php` |
| `Http::fake()` global en el arnés (evita alertas reales en pruebas) | `tests/harness/bootstrap_test.php` |
| Documentación actualizada (13 suites, 453 checks) | `docs/COMPOSICION-APP.md` |

**Regresión completa:** 13 suites, **453 OK, 0 FAIL**.

**Verificación de efectos externos:** la última ejecución en n8n sigue siendo la **133** (20:57:24). La regresión no generó ejecuciones nuevas, es decir, las pruebas ya no envían WhatsApp real.

Pendiente de decisión (no implementado): pasar `dispatchSync` a cola con worker. Hoy se mantiene síncrono con timeout de 15 s.

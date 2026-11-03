<?php

namespace App\Models;

use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Model;

class LogAuditoria extends Model
{
    protected $table = 'logs_auditoria';

    // La tabla usa fecha_hora, no created_at/updated_at.
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'accion',
        'tabla_afectada',
        'registro_id',
        'datos_previos',
        'datos_nuevos',
        'fecha_hora',
    ];

    protected $casts = [
        'datos_previos' => 'array',
        'datos_nuevos'  => 'array',
        'fecha_hora'    => 'datetime',
    ];

    // Usuario que ejecutó la acción (null = acción de sistema o usuario borrado).
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Nombre legible de la entidad afectada (ej. "Dictamen de Calidad").
    public function entidad(): string
    {
        return AuditoriaService::ENTIDADES[$this->tabla_afectada] ?? $this->tabla_afectada;
    }

    // Etiqueta legible de la acción.
    public function accionLegible(): string
    {
        return match ($this->accion) {
            'creado'     => 'Creación',
            'editado'    => 'Edición',
            'eliminado'  => 'Eliminación',
            default      => ucfirst($this->accion),
        };
    }

    // Color de la insignia por tipo de acción.
    public function colorAccion(): string
    {
        return match ($this->accion) {
            'creado'    => 'emerald',
            'editado'   => 'amber',
            'eliminado' => 'red',
            default     => 'slate',
        };
    }

    /**
     * Resumen corto de los campos afectados: "campo: antes → después".
     * Sirve para la columna principal del reporte.
     */
    public function resumenCambios(): string
    {
        if ($this->accion === 'creado') {
            $campos = array_keys($this->datos_nuevos ?? []);

            return $campos
                ? 'Registró ' . implode(', ', array_map(fn ($c) => $this->etiquetaCampo($c), $campos))
                : 'Registro creado';
        }

        if ($this->accion === 'eliminado') {
            return 'Eliminó el registro';
        }

        $partes = [];
        foreach (($this->datos_nuevos ?? []) as $campo => $nuevo) {
            $previo = $this->datos_previos[$campo] ?? '—';
            $partes[] = $this->etiquetaCampo($campo) . ': ' . $this->abreviar($previo) . ' → ' . $this->abreviar($nuevo);
        }

        return $partes ? implode(' · ', $partes) : 'Sin cambios relevantes';
    }

    // Etiqueta legible para un campo en snake_case.
    public function etiquetaCampo(string $campo): string
    {
        $mapa = [
            'descripcion'    => 'Descripción',
            'resultado'      => 'Resultado',
            'operario_id'    => 'Operario',
            'fase_id'        => 'Fase',
            'estatus_id'     => 'Estatus',
            'estatus_fase_id' => 'Estado de fase',
            'fecha_termino'  => 'Fecha de término',
            'fecha_inicio'   => 'Fecha de inicio',
            'peso_kg'        => 'Peso (kg)',
            'cantidad'       => 'Cantidad',
            'corregido'      => 'Corregido',
            'editado'        => 'Editado',
        ];

        return $mapa[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
    }

    private function abreviar($valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        $texto = is_scalar($valor) ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE);

        return mb_strlen($texto) > 40 ? mb_substr($texto, 0, 37) . '…' : $texto;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use App\Models\User;
use App\Support\FechaLocal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClienteController extends Controller
{
    // Área pública ------------------------------------------------------------

    // Formulario de ingreso del código de acceso
    public function acceso(): View
    {
        return view('portal.acceso');
    }

    // Redirige el código enviado por el formulario a la ruta legible
    public function buscar(Request $request): RedirectResponse
    {
        $codigo = strtoupper(trim((string) $request->input('codigo')));

        if ($codigo === '') {
            return back()->with('error', 'Ingresa tu código de acceso.');
        }

        return redirect()->route('consulta.ver', $codigo);
    }

    // Lista de obras del cliente dueño del código
    public function ver(string $codigo): View|RedirectResponse
    {
        $sql = User::where('rol_id', 3)
            ->where('codigo_acceso', strtoupper(trim($codigo)))
            ->where('activo', true);

        $cliente = $sql->first();

        if ($cliente === null) {
            return redirect()->route('consulta.acceso')
                ->with('error', 'El código no es válido. Verifícalo e inténtalo de nuevo.');
        }

        $obras = Obra::where('cliente_usuario_id', $cliente->id)
            ->orderBy('id', 'desc')
            ->get();

        $obrasAvance = $obras->map(function (Obra $obra) {
            $totalFases = \App\Models\Pieza::totalFasesEsperadas();
            $piezas = $obra->piezas()->with(['estatus', 'fases.estatusFase'])->get();

            $fasesCompletadas = $piezas->sum(
                fn ($pieza) => $pieza->fases->filter(fn ($f) => (bool) $f->estatusFase?->es_completada)->count()
            );

            $esperadas = $piezas->count() * $totalFases;

            return [
                'id' => $obra->id,
                'codigo_obra' => $obra->codigo_obra,
                'nombre_obra' => $obra->nombre_obra,
                'estatus_llano' => $this->estatusObraLlano($obra->estatus),
                'porcentaje' => $esperadas > 0 ? (int) round($fasesCompletadas / $esperadas * 100) : 0,
                'total_marcas' => $piezas->count(),
                'marcas_listas' => $piezas->filter(fn ($p) => $p->estatus?->nombre === 'Aprobada')->count(),
                'fecha_inicio' => $obra->fecha_inicio?->format('d/m/Y') ?? '—',
                'fecha_compromiso' => $obra->fecha_compromiso?->format('d/m/Y') ?? '—',
            ];
        });

        return view('portal.index', ['cliente' => $cliente, 'obrasAvance' => $obrasAvance]);
    }

    // Detalle de una sola obra: todas sus marcas y las fases de cada una
    public function obra(string $codigo, int $obraId): View|RedirectResponse
    {
        $cliente = User::where('rol_id', 3)
            ->where('codigo_acceso', strtoupper(trim($codigo)))
            ->where('activo', true)
            ->first();

        $obra = Obra::with([
            'piezas.tipoEstructura',
            'piezas.estatus',
            'piezas.fases.fase',
            'piezas.fases.estatusFase',
        ])->find($obraId);

        if ($cliente === null || $obra === null || (int) $obra->cliente_usuario_id !== (int) $cliente->id) {
            abort(404);
        }

        $marcas = $obra->piezas
            ->sortBy(fn ($p) => $p->clave_marca_pieza)
            ->map(function ($pieza) {
                return [
                    'pieza' => $pieza,
                    'clave' => $pieza->clave_marca_pieza,
                    'tipo' => $pieza->tipoEstructura?->nombre ?? '—',
                    'cantidad' => $pieza->cantidad,
                    'peso_kg' => $pieza->peso_kg,
                    'estatus_llano' => $this->estatusPiezaLlano($pieza->estatus?->nombre),
                    'porcentaje' => $pieza->porcentajeAvance(),
                    'fases' => $pieza->fases
                        ->sortBy(fn ($f) => $f->fase?->orden_secuencia ?? PHP_INT_MAX)
                        ->values()
                        ->map(fn ($f) => [
                            'nombre' => $f->fase?->nombre,
                            'estado_llano' => $this->estatusFaseLlano($f->estatusFase?->nombre),
                            'fecha_inicio' => FechaLocal::formato($f->fecha_inicio, 'd/m/Y'),
                            'fecha_dictamen' => FechaLocal::formato($f->fecha_dictamen, 'd/m/Y'),
                            'fecha_termino' => FechaLocal::formato($f->fecha_termino, 'd/m/Y'),
                        ]),
                ];
            })
            ->values();

        return view('portal.show', [
            'cliente' => $cliente,
            'obra' => $obra,
            'marcas' => $marcas,
        ]);
    }

    // Área administrativa (jefatura) -----------------------------------------

    public function index(): View
    {
        $clientes = User::where('rol_id', 3)
            ->withCount('obrasAsignadas')
            ->orderBy('id', 'desc')
            ->get();

        return view('clientes.index', ['clientes' => $clientes]);
    }

    public function create(): View
    {
        $obras = Obra::orderBy('id', 'desc')
            ->withCount('piezas')
            ->get(['id', 'codigo_obra', 'nombre_obra']);

        return view('clientes.create', ['obras' => $obras]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:150', 'unique:usuarios,email'],
            'obras' => ['nullable', 'array'],
            'obras.*' => ['exists:obras,id'],
        ]);

        $email = $datos['email'] ?? sprintf('cliente%s@famep.com', Str::lower(Str::random(6)));

        $cliente = User::create([
            'nombre' => $datos['nombre'],
            'email' => $email,
            'password_hash' => Hash::make(Str::random(32)),
            'rol_id' => 3,
            'activo' => true,
            'codigo_acceso' => $this->generarCodigo(),
        ]);

        if (! empty($datos['obras'])) {
            Obra::whereIn('id', $datos['obras'])->update(['cliente_usuario_id' => $cliente->id]);
        }

        return redirect()->route('clientes.index')
            ->with('success', "Cliente creado. Código de acceso: {$cliente->codigo_acceso}");
    }

    private function generarCodigo(): string
    {
        do {
            $codigo = 'FAMEP-' . strtoupper(Str::random(4));
        } while (User::where('codigo_acceso', $codigo)->exists());

        return $codigo;
    }

    // Mapeo digerible para el cliente ----------------------------------------

    private function estatusObraLlano(?string $estatus): string
    {
        return match ($estatus) {
            'Pendiente' => 'Por iniciar',
            'Activa' => 'En fabricación',
            'Finalizada' => 'Terminada',
            default => $estatus ?? '—',
        };
    }

    private function estatusPiezaLlano(?string $estatus): string
    {
        return match ($estatus) {
            'Pendiente' => 'Por iniciar',
            'En Proceso' => 'En fabricación',
            'En Revisión' => 'En espera de Calidad',
            'Detenida' => 'Detenida',
            'Aprobada' => 'Terminada',
            default => $estatus ?? '—',
        };
    }

    private function estatusFaseLlano(?string $estatus): string
    {
        return match ($estatus) {
            'En Proceso' => 'En trabajo',
            'En Reproceso' => 'En corrección',
            'Aprobada por Calidad' => 'Aprobada por Calidad',
            'Completada' => 'Terminada',
            'Rechazada' => 'Rechazada',
            default => $estatus ?? 'Sin registrar',
        };
    }
}
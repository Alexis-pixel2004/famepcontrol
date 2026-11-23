<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\PiezaController;
use App\Http\Controllers\FasePiezaController;
use App\Http\Controllers\ObservacionCalidadController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\OperarioController;
use App\Http\Controllers\WhatsAppController;

// Redirigir la raíz al login
Route::get('/', function () {
    return redirect()->route('login');
});

// Rutas de invitados (sin iniciar sesión)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Portal público de consulta: el cliente entra solo con su código (sin sesión)
Route::get('/consulta', [ClienteController::class, 'acceso'])->name('consulta.acceso');
Route::post('/consulta', [ClienteController::class, 'buscar'])->name('consulta.buscar');
Route::get('/consulta/{codigo}', [ClienteController::class, 'ver'])->name('consulta.ver');
Route::get('/consulta/{codigo}/obra/{obraId}', [ClienteController::class, 'obra'])->name('consulta.obra');

// Rutas protegidas (requieren sesión activa de un jefe, no de un cliente)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Perfil propio: cambio de contraseña (Producción y Calidad).
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password');

    Route::middleware('jefatura')->group(function () {
        // Panel Principal
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Gestión de Obras
        Route::get('/obras', [ObraController::class, 'index'])->name('obras.index');
        Route::get('/obras/crear', [ObraController::class, 'create'])->name('obras.create');
        Route::post('/obras', [ObraController::class, 'store'])->name('obras.store');
        Route::get('/obras/{id}/editar', [ObraController::class, 'edit'])->name('obras.edit');
        Route::put('/obras/{id}', [ObraController::class, 'update'])->name('obras.update');
        Route::delete('/obras/{id}', [ObraController::class, 'destroy'])->name('obras.destroy');
        Route::get('/obras/{id}', [ObraController::class, 'show'])->name('obras.show');

        // Gestión de Piezas por Obra
        Route::post('/obras/{obraId}/piezas', [PiezaController::class, 'store'])->name('piezas.store');
        Route::get('/piezas/{id}/editar', [PiezaController::class, 'edit'])->name('piezas.edit');
        Route::put('/piezas/{id}', [PiezaController::class, 'update'])->name('piezas.update');
        Route::delete('/piezas/{id}', [PiezaController::class, 'destroy'])->name('piezas.destroy');

        // Seguimiento de Fases
        Route::get('/piezas/{piezaId}', [FasePiezaController::class, 'show'])->name('piezas.show');
        Route::post('/piezas/{piezaId}/fases', [FasePiezaController::class, 'store'])->name('fases.store');
        Route::get('/fases/{fasePiezaId}/editar', [FasePiezaController::class, 'edit'])->name('fases.edit');
        Route::put('/fases/{fasePiezaId}', [FasePiezaController::class, 'update'])->name('fases.update');
        Route::delete('/fases/{fasePiezaId}', [FasePiezaController::class, 'destroy'])->name('fases.destroy');

        // Control de Calidad y Evidencias
        Route::post('/fases/{fasePiezaId}/calidad', [ObservacionCalidadController::class, 'store'])->name('calidad.store');
        Route::get('/calidad/{id}/editar', [ObservacionCalidadController::class, 'edit'])->name('calidad.edit');
        Route::put('/calidad/{id}', [ObservacionCalidadController::class, 'update'])->name('calidad.update');
        Route::delete('/calidad/{id}', [ObservacionCalidadController::class, 'destroy'])->name('calidad.destroy');
        Route::post('/fases/{fasePiezaId}/evidencia', [EvidenciaController::class, 'store'])->name('evidencia.store');

        //Obtener automaticamente las notificaciones de calidad para el dropdown
        Route::get('/api/notificaciones-calidad', [DashboardController::class, 'getNotificacionesApi'])->name('api.notificaciones');

        // Gestión de cuentas de clientes (Visitantes)
        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/crear', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/clientes/{id}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::put('/clientes/{id}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('/clientes/{id}', [ClienteController::class, 'destroy'])->name('clientes.destroy');

        // Reportes (Producción y Calidad)
        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/avance-obra', [ReporteController::class, 'avanceObra'])->name('reportes.avance-obra');
        Route::get('/reportes/calidad', [ReporteController::class, 'calidad'])->name('reportes.calidad');
        Route::get('/reportes/productividad', [ReporteController::class, 'productividad'])->name('reportes.productividad');
        Route::get('/reportes/hallazgos', [ReporteController::class, 'hallazgos'])->name('reportes.hallazgos');
        Route::get('/reportes/historial', [ReporteController::class, 'historial'])->name('reportes.historial');
        Route::get('/reportes/auditoria', [ReporteController::class, 'auditoria'])->name('reportes.auditoria');

        // Gestión de Operarios (solo Jefe de Producción; el controller revalida el rol)
        Route::get('/operarios', [OperarioController::class, 'index'])->name('operarios.index');
        Route::get('/operarios/crear', [OperarioController::class, 'create'])->name('operarios.create');
        Route::post('/operarios', [OperarioController::class, 'store'])->name('operarios.store');
        Route::get('/operarios/{id}/editar', [OperarioController::class, 'edit'])->name('operarios.edit');
        Route::put('/operarios/{id}', [OperarioController::class, 'update'])->name('operarios.update');
        Route::delete('/operarios/{id}', [OperarioController::class, 'destroy'])->name('operarios.destroy');

        // Vinculación de WhatsApp: solo Jefe de Calidad (el controller revalida el rol)
        Route::get('/whatsapp/qr', [WhatsAppController::class, 'index'])->name('whatsapp.qr');
        Route::get('/whatsapp/qr/estado', [WhatsAppController::class, 'estado'])->name('whatsapp.qr.estado');
    });
});
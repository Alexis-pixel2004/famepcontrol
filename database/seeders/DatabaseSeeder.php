<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Operario;
use App\Models\Obra;
use App\Models\Pieza;
use App\Models\CatRol;
use App\Models\CatTipoEstructura;
use App\Models\CatEstatusPieza;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $rolProduccion = CatRol::where('nombre', 'Jefe de Producción')->first();
        $rolCalidad = CatRol::where('nombre', 'Jefe de Calidad')->first();
        
        $tipoPesada = CatTipoEstructura::where('nombre', 'Pesada')->first();
        $estatusPendiente = CatEstatusPieza::where('nombre', 'Pendiente')->first();

        // Inserta solo si no existe el email (si existe, lo actualiza)
        User::updateOrCreate(
            ['email' => 'produccion@famep.com'],
            [
                'nombre' => 'Carlos Mendoza',
                'password_hash' => Hash::make('famep123'),
                'rol_id' => $rolProduccion->id,
                'activo' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'calidad@famep.com'],
            [
                'nombre' => 'Ana Laura Torres',
                'password_hash' => Hash::make('famep123'),
                'rol_id' => $rolCalidad->id,
                'activo' => true,
            ]
        );

        // Operario
        Operario::updateOrCreate(
            ['clave_empleado' => 'OP-001'],
            [
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
                'especialidad' => 'Armador Técnico',
                'activo' => true,
            ]
        );

        // Obra de prueba
        $obraPrueba = Obra::updateOrCreate(
            ['codigo_obra' => 'OB-2026-001'],
            [
                'nombre_obra' => 'Nave Industrial TechPark',
                'cliente' => 'Construcciones del Norte S.A.',
                'fecha_inicio' => '2026-03-01',
                'fecha_compromiso' => '2026-06-30',
                'estatus' => 'Activa',
            ]
        );

        // Pieza
        Pieza::updateOrCreate(
            ['obra_id' => $obraPrueba->id, 'clave_marca_pieza' => 'TB1'],
            [
                'tipo_estructura_id' => $tipoPesada->id,
                'estatus_id' => $estatusPendiente->id,
                'peso_kg' => 450.50,
                'cantidad' => 4,
            ]
        );

        $this->command->info('¡Datos de prueba insertados o actualizados con éxito!');
    }
}
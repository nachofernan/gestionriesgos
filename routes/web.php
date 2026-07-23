<?php

use App\Http\Controllers\Auditoria\ActualizacionController;
use App\Http\Controllers\Auditoria\ControlController;
use App\Http\Controllers\Auditoria\ObjetivoController;
use App\Http\Controllers\Auditoria\PendienteController;
use App\Http\Controllers\Auditoria\PlanAccionController;
use App\Http\Controllers\Auditoria\RiesgoController;
use App\Http\Controllers\Auditoria\TareaController;
use App\Http\Controllers\Auditoria\VencimientoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('auditoria.panel.index');
});

Route::get('/dashboard', function () {
    return redirect()->route('auditoria.panel.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->prefix('auditoria')->name('auditoria.')->group(function () {

    // -------------------------------------------------------------------
    // Panel de situación (mapa de calor, distribución, accesos rápidos)
    // -------------------------------------------------------------------
    Route::view('panel', 'auditoria.panel.index')->name('panel.index');

    // -------------------------------------------------------------------
    // Riesgos
    // -------------------------------------------------------------------
    Route::resource('riesgos', RiesgoController::class)
        ->parameters(['riesgos' => 'riesgo']);
    Route::get('riesgos/{riesgo}/recalcular', [RiesgoController::class, 'recalcular'])
        ->name('riesgos.recalcular');
    Route::post('riesgos/{riesgo}/recalcular', [RiesgoController::class, 'recalcularStore'])
        ->name('riesgos.recalcular.store');
    Route::post('riesgos/{riesgo}/controles', [RiesgoController::class, 'asociarControles'])
        ->name('riesgos.controles');
    Route::post('riesgos/{riesgo}/objetivos', [RiesgoController::class, 'asociarObjetivos'])
        ->name('riesgos.objetivos');
    Route::post('riesgos/{riesgo}/validar', [RiesgoController::class, 'validar'])
        ->name('riesgos.validar');
    Route::post('riesgos/{riesgo}/aprobar', [RiesgoController::class, 'aprobar'])
        ->name('riesgos.aprobar');
    Route::post('riesgos/{riesgo}/rechazar', [RiesgoController::class, 'rechazar'])
        ->name('riesgos.rechazar');
    Route::post('riesgos/{riesgo}/actualizaciones', [ActualizacionController::class, 'storeRiesgo'])
        ->name('riesgos.actualizaciones.store');

    // -------------------------------------------------------------------
    // Controles
    // -------------------------------------------------------------------
    Route::resource('controles', ControlController::class)
        ->parameters(['controles' => 'control']);
    Route::post('controles/{control}/validar', [ControlController::class, 'validar'])
        ->name('controles.validar');
    Route::post('controles/{control}/aprobar', [ControlController::class, 'aprobar'])
        ->name('controles.aprobar');
    Route::post('controles/{control}/rechazar', [ControlController::class, 'rechazar'])
        ->name('controles.rechazar');
    Route::post('controles/{control}/actualizaciones', [ActualizacionController::class, 'storeControl'])
        ->name('controles.actualizaciones.store');

    // -------------------------------------------------------------------
    // Objetivos
    // -------------------------------------------------------------------
    Route::resource('objetivos', ObjetivoController::class)
        ->parameters(['objetivos' => 'objetivo']);
    Route::post('objetivos/{objetivo}/validar', [ObjetivoController::class, 'validar'])
        ->name('objetivos.validar');
    Route::post('objetivos/{objetivo}/aprobar', [ObjetivoController::class, 'aprobar'])
        ->name('objetivos.aprobar');
    Route::post('objetivos/{objetivo}/rechazar', [ObjetivoController::class, 'rechazar'])
        ->name('objetivos.rechazar');
    Route::post('objetivos/{objetivo}/actualizaciones', [ActualizacionController::class, 'storeObjetivo'])
        ->name('objetivos.actualizaciones.store');

    // -------------------------------------------------------------------
    // Planes de acción
    // -------------------------------------------------------------------
    Route::resource('planes', PlanAccionController::class)
        ->parameters(['planes' => 'planAccion']);
    Route::post('planes/{planAccion}/tareas', [PlanAccionController::class, 'asociarTareas'])
        ->name('planes.tareas');
    Route::post('planes/{planAccion}/validar', [PlanAccionController::class, 'validar'])
        ->name('planes.validar');
    Route::post('planes/{planAccion}/aprobar', [PlanAccionController::class, 'aprobar'])
        ->name('planes.aprobar');
    Route::post('planes/{planAccion}/rechazar', [PlanAccionController::class, 'rechazar'])
        ->name('planes.rechazar');
    Route::post('planes/{planAccion}/actualizaciones', [ActualizacionController::class, 'storePlan'])
        ->name('planes.actualizaciones.store');

    // -------------------------------------------------------------------
    // Tareas
    // -------------------------------------------------------------------
    Route::resource('tareas', TareaController::class)
        ->parameters(['tareas' => 'tarea']);
    Route::post('tareas/{tarea}/validar', [TareaController::class, 'validar'])
        ->name('tareas.validar');
    Route::post('tareas/{tarea}/aprobar', [TareaController::class, 'aprobar'])
        ->name('tareas.aprobar');
    Route::post('tareas/{tarea}/rechazar', [TareaController::class, 'rechazar'])
        ->name('tareas.rechazar');
    Route::post('tareas/{tarea}/actualizaciones', [ActualizacionController::class, 'storeTarea'])
        ->name('tareas.actualizaciones.store');

    // -------------------------------------------------------------------
    // Actualizaciones (transiciones de estado)
    // -------------------------------------------------------------------
    Route::post('actualizaciones/{actualizacion}/validar', [ActualizacionController::class, 'validar'])
        ->name('actualizaciones.validar');
    Route::post('actualizaciones/{actualizacion}/aprobar', [ActualizacionController::class, 'aprobar'])
        ->name('actualizaciones.aprobar');
    Route::post('actualizaciones/{actualizacion}/rechazar', [ActualizacionController::class, 'rechazar'])
        ->name('actualizaciones.rechazar');
    Route::get('actualizaciones/{actualizacion}/adjuntos/{media}', [ActualizacionController::class, 'descargarAdjunto'])
        ->name('actualizaciones.adjuntos.download');

    // -------------------------------------------------------------------
    // Pendientes (qué tiene que validar/aprobar el usuario logueado)
    // -------------------------------------------------------------------
    Route::get('pendientes', [PendienteController::class, 'index'])->name('pendientes.index');
    Route::get('pendientes/pdf', [PendienteController::class, 'pdf'])->name('pendientes.pdf');

    // -------------------------------------------------------------------
    // Vencimientos (tareas ordenadas por fecha: vencidas / por vencer)
    // -------------------------------------------------------------------
    Route::get('vencimientos', [VencimientoController::class, 'index'])->name('vencimientos.index');
});

require __DIR__.'/auth.php';

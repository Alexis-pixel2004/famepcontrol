document.addEventListener('DOMContentLoaded', () => {
    const selectFase = document.getElementById('selectFase');
    const selectOperario = document.getElementById('selectOperario');
    const selectEstatus = document.getElementById('selectEstatusFase');
    const optionCompletada = document.getElementById('optionCompletada');
    const optionEnProceso = document.getElementById('optionEnProceso');
    const avisoOperario = document.getElementById('avisoOperario');

    if (!selectFase || !selectOperario || !selectEstatus || !optionCompletada) return;

    // ¿El operario está habilitado para la fase seleccionada?
    // Sin fases configuradas (lista vacía) = sin restricción: no se bloquea.
    const estaHabilitado = (option, faseId) => {
        const crudo = option.getAttribute('data-fases-habilitadas');
        if (crudo === null || crudo === '') return true; // sin restricción
        return crudo.split(',').map(v => v.trim()).includes(String(faseId));
    };

    const actualizarValidaciones = () => {
        const opcionSeleccionada = selectFase.options[selectFase.selectedIndex];

        if (!opcionSeleccionada || !selectFase.value) return;

        // 1. Solo se puede cerrar la fase si Calidad ya la aprobó.
        //    El select de estado trabaja con ids de cat_estatus_fase,
        //    por eso se comparan valores y no etiquetas.
        const estaAprobado = opcionSeleccionada.getAttribute('data-aprobado') === 'true';

        if (!estaAprobado) {
            optionCompletada.disabled = true;
            optionCompletada.textContent = 'Completada  (Requiere Aprobación de Calidad)';
            if (optionEnProceso && selectEstatus.value === optionCompletada.value) {
                selectEstatus.value = optionEnProceso.value;
            }
        } else {
            // Aprobada por Calidad: la única transición que admite
            // Producción es cerrarla, así que se fuerza "Completada".
            optionCompletada.disabled = false;
            optionCompletada.textContent = 'Completada';
            if (optionEnProceso) {
                optionEnProceso.disabled = true;
                optionEnProceso.textContent = 'En Proceso  (La fase ya fue aprobada)';
                if (selectEstatus.value === optionEnProceso.value) {
                    selectEstatus.value = optionCompletada.value;
                }
            }
        }

        // 2. Validación de operario: el mismo operario queda bloqueado
        //    solo si la fase sigue "En Proceso". Si volvió de Calidad
        //    (reproceso) el mismo operario puede retomar el trabajo.
        const operarioActualId = opcionSeleccionada.getAttribute('data-operario-id');
        const esEnProceso = opcionSeleccionada.getAttribute('data-en-curso') === 'true';

        let hayIncompatibles = false;
        let incompatibles = [];

        Array.from(selectOperario.options).forEach(option => {
            if (!option.value) return;

            const textoOriginal = option.getAttribute('data-texto-original') || option.textContent;
            const esMismoOperario = operarioActualId && option.value === operarioActualId;
            const habilitado = estaHabilitado(option, selectFase.value);

            if (esEnProceso && esMismoOperario) {
                option.disabled = true;
                option.textContent = `${textoOriginal}  (Asignado Previamente)`;
                option.hidden = false;

                if (selectOperario.value === option.value) {
                    selectOperario.value = '';
                }
            } else if (!habilitado) {
                // No puede ejecutar esta fase: se bloquea y se oculta.
                option.disabled = true;
                option.hidden = true;
                option.textContent = `${textoOriginal}  (No habilitado para esta fase)`;
                hayIncompatibles = true;
                incompatibles.push(textoOriginal);

                if (selectOperario.value === option.value) {
                    selectOperario.value = '';
                }
            } else {
                option.disabled = false;
                option.hidden = false;
                option.textContent = textoOriginal;
            }
        });

        // 3. Aviso en la UI cuando hay operarios bloqueados por su especialidad.
        if (avisoOperario) {
            if (hayIncompatibles) {
                avisoOperario.textContent = `Hay ${incompatibles.length} operario(s) no habilitado(s) para esta fase; se ocultan de la lista.`;
                avisoOperario.classList.remove('hidden');
            } else {
                avisoOperario.textContent = '';
                avisoOperario.classList.add('hidden');
            }
        }
    };

    // Escuchar cambios en ambos selectores
    selectFase.addEventListener('change', actualizarValidaciones);
    selectEstatus.addEventListener('change', actualizarValidaciones);
    actualizarValidaciones();
});

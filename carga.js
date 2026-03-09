// Modificar la función de manejo de archivos para solo aceptar TXT
document.getElementById('fileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validar que sea archivo TXT
    const extension = file.name.split('.').pop().toLowerCase();
    if (extension !== 'txt') {
        showAlert('danger', 'Solo se permiten archivos de texto (.txt)');
        this.value = '';
        return;
    }
    
    handleFiles();
});

// Actualizar las opciones de formato para solo mostrar TXT
document.addEventListener('DOMContentLoaded', function() {
    const formatoSelect = document.getElementById('formatoArchivo');
    formatoSelect.innerHTML = '<option value="txt">Archivo de Texto (.txt)</option>';
    
    // Actualizar texto en la interfaz
    const instrucciones = document.querySelector('.alert-info ul');
    instrucciones.innerHTML = `
        <li>Archivos TXT: Delimitado por pipes (|)</li>
        <li>Formato: CODIGO_EMPLEADO|NOMBRE|MONTO|FECHA</li>
        <li>Ejemplo: EMP001|Juan Pérez|100.00|2023-07-15</li>
        <li>Codificación: UTF-8</li>
        <li>Tamaño máximo: 10MB</li>
    `;
    
    // Actualizar botones de plantilla
    document.getElementById('downloadExcelTemplate').style.display = 'none';
    document.getElementById('downloadTextTemplate').textContent = 'Descargar Plantilla TXT';
});
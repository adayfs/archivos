document.addEventListener('DOMContentLoaded', function () {
  const slider = document.getElementById('slider_temp_hp');
  const display = document.getElementById('display_cs_temp_hp');
  const hidden = document.getElementById('cs_hp_temp');

  if (!slider || !display || !hidden || typeof HP_TEMP_AJAX === 'undefined') return;

  let timeout = null;

  function guardarVidaTemporal(valor) {
    fetch(HP_TEMP_AJAX.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'guardar_hp_temporal',
        post_id: HP_TEMP_AJAX.post_id,
        valor: valor
      })
    })
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        console.error("Error al guardar HP temporal:", data.message);
      }
    })
    .catch(error => {
      console.error("Error AJAX HP temporal:", error);
    });
  }

  function scheduleSave(value) {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
      guardarVidaTemporal(value);
    }, 500);
  }

  slider.addEventListener('input', () => {
    const val = parseInt(slider.value, 10) || 0;
    display.textContent = val;
    hidden.value = val;
    scheduleSave(val);
  });

  display.addEventListener('input', () => {
    const val = parseInt(display.textContent, 10) || 0;
    slider.value = val;
    hidden.value = val;
    scheduleSave(val);
  });
});


// internestichedef/stiche.js
window.InterneStiche = (function(){
  const API_GET  = 'internestichedef/api_get_stiche.php';
  const API_SAVE = 'internestichedef/api_save_stiche.php';

  const $table    = () => $('#stichdefTabelle');
  const $btnLoad  = () => $('#btnLoad');
  const $btnSave  = () => $('#btnSave');
  const $btnReset = () => $('#btnReset');

  let originalData = {};

  function gatherRows(){
    const out = {};
    $table().find('tbody tr').each(function(){
      const stich = $(this).data('stich');
      const n1  = $(this).find('.nr1-input').val().trim();
      const n2  = $(this).find('.nr2-input').val().trim();
      const n3  = $(this).find('.nr3-input').val().trim();
      out[stich] = { nummer1: n1, nummer2: n2, nummer3: n3 };
    });
    return out;
  }

  function fillRows(rows){
    $table().find('tbody tr').each(function(){
      const stich = $(this).data('stich');
      const v = rows[stich] || {nummer1:'', nummer2:'', nummer3:''};
      $(this).find('.nr1-input').val(v.nummer1 || '');
      $(this).find('.nr2-input').val(v.nummer2 || '');
      $(this).find('.nr3-input').val(v.nummer3 || '');
    });
  }

  function setDirtyState(enabled){
    $btnSave().prop('disabled', !enabled);
    $btnReset().prop('disabled', !enabled);
  }

  function bindDirtyWatcher(){
    $table().on('input change', 'input', function(){
      const current = gatherRows();
      const isDirty = JSON.stringify(current) !== JSON.stringify(originalData);
      setDirtyState(isDirty);
    });
  }

  async function load(){
    try{
      $btnLoad().prop('disabled', true);
      const res = await fetch(API_GET, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: CSRF_TOKEN })
      });
      const data = await res.json();
      if(!data.success){ throw new Error(data.message || 'Unbekannter Fehler'); }
      fillRows(data.rows || {});
      originalData = gatherRows();
      setDirtyState(false);
      toast('Daten geladen.', 'success');
    }catch(err){
      console.error(err);
      toast('Laden fehlgeschlagen: ' + err.message, 'error');
    }finally{
      $btnLoad().prop('disabled', false);
    }
  }

  async function save(){
    const rows = gatherRows();
    try{
      $btnSave().prop('disabled', true);
      const res = await fetch(API_SAVE, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, rows })
      });
      const data = await res.json();
      if(!data.success){ throw new Error(data.message || 'Unbekannter Fehler'); }
      originalData = gatherRows();
      setDirtyState(false);
      toast('Erfolgreich gespeichert.', 'success');
    }catch(err){
      console.error(err);
      toast('Speichern fehlgeschlagen: ' + err.message, 'error');
    }
  }

  function reset(){
    fillRows(originalData);
    setDirtyState(false);
    toast('Änderungen verworfen.', 'info');
  }

  function toast(msg, type){
    msvToast(msg, type);
  }

  function bind(){
    $btnLoad().on('click', load);
    $btnSave().on('click', save);
    $btnReset().on('click', reset);

    // Ctrl/Cmd+S
    $(document).on('keydown', function(e){
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's'){
        e.preventDefault();
        if(!$btnSave().prop('disabled')) save();
      }
    });
  }

  function init(){
    bind();
    bindDirtyWatcher();
    // Beim ersten Öffnen direkt laden
    load();
  }

  return { init };
})();

(function(){
  document.addEventListener('click', function(e){
    const b=e.target.closest('[data-password-toggle]');
    if(!b) return;
    const id=b.getAttribute('data-password-toggle');
    const input=document.getElementById(id);
    if(!input) return;
    const show=input.type==='password';
    input.type=show?'text':'password';
    b.textContent=show?'Ocultar':'Ver';
  });

  const role=document.getElementById('userRole');
  function syncRole(){
    if(!role) return;
    const admin=role.value==='admin';
    document.querySelectorAll('.permission-grid input[type="checkbox"]').forEach(function(cb){
      cb.disabled=admin;
    });
    const note=document.getElementById('adminPermissionNote');
    if(note) note.hidden=!admin;
  }
  if(role){ role.addEventListener('change',syncRole); syncRole(); }

  document.querySelectorAll('.permission-row').forEach(function(row){
    const view=row.querySelector('input[data-perm="view"]');
    const edit=row.querySelector('input[data-perm="edit"]');
    if(!view||!edit) return;
    edit.addEventListener('change',function(){ if(edit.checked) view.checked=true; });
    view.addEventListener('change',function(){ if(!view.checked) edit.checked=false; });
  });
})();

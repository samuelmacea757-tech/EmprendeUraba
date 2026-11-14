// Confirmaciones en acciones destructivas: <a data-confirm="¿Seguro?">
document.addEventListener('click', e => {
  const el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.dataset.confirm)) e.preventDefault();
});
// Vista previa de imágenes: <input type="file" data-preview="#idImg">
document.querySelectorAll('input[type=file][data-preview]').forEach(inp => {
  inp.addEventListener('change', () => {
    const f = inp.files[0], t = document.querySelector(inp.dataset.preview);
    if (f && t) t.src = URL.createObjectURL(f);
  });
});
// Mostrar/ocultar campos según tipo (producto/servicio)
const tipo = document.querySelector('#tipo');
if (tipo) {
  const toggle = () => document.querySelectorAll('[data-solo]').forEach(el => el.classList.toggle('d-none', el.dataset.solo !== tipo.value));
  tipo.addEventListener('change', toggle); toggle();
}
// Calcula el total del pedido en tiempo real: inputs .qty con data-precio
const totalEl = document.querySelector('#totalPedido');
if (totalEl) {
  const calc = () => {
    let t = 0; document.querySelectorAll('.qty').forEach(i => t += (parseInt(i.value) || 0) * parseFloat(i.dataset.precio));
    totalEl.textContent = '$' + t.toLocaleString('es-CO');
  };
  document.querySelectorAll('.qty').forEach(i => i.addEventListener('input', calc)); calc();
}
// Filas dinámicas de venta
const addRow = document.querySelector('#addRow');
if (addRow) {
  addRow.addEventListener('click', () => {
    const tpl = document.querySelector('#rows .row-item').cloneNode(true);
    tpl.querySelectorAll('input').forEach(i => i.value = i.type === 'number' ? 1 : ''); tpl.querySelector('select').selectedIndex = 0;
    document.querySelector('#rows').appendChild(tpl);
  });
  document.addEventListener('click', e => { if (e.target.closest('.rm-row') && document.querySelectorAll('.row-item').length > 1) e.target.closest('.row-item').remove(); });
}

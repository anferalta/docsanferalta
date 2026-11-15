(() => {
'use strict';
const CONTACT_EMAIL = 'suporte@anferalta.com';
const CONTACT_SUBJECT = 'Pedido de demonstração — AnferaltaDocs';
const CONTEXTS = ['Escolas', 'Empresas', 'Instituições', 'Particulares', 'Outro contexto'];

function buildDemoRequest(input) {
  const name = String(input.name ?? '').trim();
  const email = String(input.email ?? '').trim();
  const organization = String(input.organization ?? '').trim();
  const interest = String(input.interest ?? 'Outro contexto');
  const message = String(input.message ?? '').trim();
  if (!name || name.length > 90 || /[\r\n\x00]/.test(name)) throw new Error('Indique um nome válido, até 90 caracteres.');
  if (email.length > 180 || !/^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/.test(email)) throw new Error('Indique um endereço de email válido.');
  if (organization.length > 120 || /[\r\n\x00]/.test(organization)) throw new Error('Indique uma organização válida, até 120 caracteres.');
  if (!CONTEXTS.includes(interest)) throw new Error('Escolha um contexto de utilização.');
  if (message.length > 1200 || /\x00/.test(message)) throw new Error('A descrição deve ter até 1200 caracteres.');
  const body = [CONTACT_SUBJECT, '', 'Nome: ' + name, 'Email: ' + email,
    ...(organization ? ['Organização: ' + organization] : []), 'Contexto: ' + interest,
    ...(message ? ['', 'O que gostaria de organizar:', message] : []),
    '', 'Gostaria de conhecer o AnferaltaDocs e combinar uma demonstração.'].join('\r\n');
  return { recipient: CONTACT_EMAIL, subject: CONTACT_SUBJECT, body };
}

function buildMailtoUrl(request) {
  // Recipient and subject are fixed; visitor data is encoded only into the body.
  return 'mailto:' + CONTACT_EMAIL + '?subject=' + encodeURIComponent(CONTACT_SUBJECT) + '&body=' + encodeURIComponent(request.body);
}

const $=(s,p=document)=>p.querySelector(s); const $$=(s,p=document)=>[...p.querySelectorAll(s)]; function closeMenu(){}
const contactDialog = $('#contact-dialog');
const privacyDialog = $('#privacy-dialog');
function openDialog(dialog) { closeMenu(); dialog.showModal(); document.body.classList.add('dialog-open'); }
function closeDialog(dialog) { dialog.close(); }
$$('dialog').forEach(dialog => { $('[data-close]', dialog).addEventListener('click', () => closeDialog(dialog)); dialog.addEventListener('close', () => { if (!document.querySelector('dialog[open]')) document.body.classList.remove('dialog-open'); }); dialog.addEventListener('click', event => { if (event.target !== dialog) return; const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) closeDialog(dialog); }); });
$$('.demo-request').forEach(button => button.addEventListener('click', () => { const interest = button.dataset.interest; if (interest) $('#contact-interest').value = interest; $('#contact-result').hidden = true; $('#contact-form-view').hidden = false; $('#copy-status').textContent = ''; $('#contact-error').hidden = true; contactDialog.setAttribute('aria-labelledby', 'contact-title'); openDialog(contactDialog); }));
$('#privacy-open').addEventListener('click', () => openDialog(privacyDialog));
$('#contact-form').addEventListener('submit', event => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(event.target));
  let request;
  try { request = buildDemoRequest(data); }
  catch (error) { $('#contact-error').textContent = error.message; $('#contact-error').hidden = false; $('#contact-error').focus(); return; }
  $('#contact-error').hidden = true;
  $('#contact-summary').value = request.body;
  $('#open-email').href = buildMailtoUrl(request);
  $('#contact-form-view').hidden = true; $('#contact-result').hidden = false; $('#copy-status').textContent = '';
  contactDialog.setAttribute('aria-labelledby', 'contact-result-title');
  $('#contact-result h2').tabIndex = -1; $('#contact-result h2').focus();
});
$('#contact-name').addEventListener('input', event => event.target.setCustomValidity(''));
$('#edit-request').addEventListener('click', () => { $('#contact-form-view').hidden = false; $('#contact-result').hidden = true; contactDialog.setAttribute('aria-labelledby', 'contact-title'); $('#contact-name').focus(); });
$('#copy-summary').addEventListener('click', async () => {
  const text = $('#contact-summary').value;
  try { if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable'); await navigator.clipboard.writeText(text); $('#copy-status').textContent = 'Pedido copiado. Envie-o para suporte@anferalta.com pelo seu serviço de email.'; }
  catch { $('#contact-summary').focus(); $('#contact-summary').select(); $('#copy-status').textContent = 'Selecione “Copiar” no seu dispositivo ou use Ctrl+C / ⌘C.'; }
});


// Enable interactive controls only after their handlers have been registered.
document.body.classList.remove('no-js');

const context=document.body.dataset.context;
const examples={"Escolas": [["Requerimentos", "Organize pedidos de declarações e outros documentos administrativos por categoria."], ["Correspondência", "Identifique comunicações recebidas e acompanhe o encaminhamento para os responsáveis."], ["Arquivo administrativo", "Mantenha um contexto de consulta para a documentação de trabalho da escola."]], "Empresas": [["Contratos", "Organize contratos de prestação de serviços e a documentação associada ao trabalho."], ["Correspondência comercial", "Registe comunicações e encaminhe os documentos para a equipa responsável."], ["Documentação interna", "Estruture categorias e responsabilidades de consulta para o arquivo da empresa."]], "Particulares": [["Contratos pessoais", "Reúna os contratos que pretende manter organizados e disponíveis para consulta."], ["Comprovativos", "Separe comprovativos e correspondência por categorias que reconheça facilmente."], ["Atividade independente", "Avalie a organização de documentação de trabalho num arquivo com estrutura própria."]]}[context];
let selected=0;
$$('[data-example]').forEach(button=>button.addEventListener('click',()=>{
 selected=Number(button.dataset.example);
 $$('[data-example]').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));
 $('#example-description').textContent=examples[selected][1];
 $('#example-request').textContent='Conversar sobre '+examples[selected][0].toLowerCase()+' →';
}));
$$('.demo-request').forEach(button=>button.addEventListener('click',()=>{
 $('#contact-interest').value=context;
 if(button.id==='example-request') $('#contact-message').value='Gostaria de conhecer a organização de '+examples[selected][0].toLowerCase()+'.';
}));

})();

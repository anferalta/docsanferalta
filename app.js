// AnferaltaDocs portable delivery: no imports or network calls.
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


'use strict';
// Presentation-only interactions. No network requests, cookies or browser storage.
const $ = (selector, parent = document) => parent.querySelector(selector);
const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];

const menuToggle = $('#menu-toggle');
const menu = $('#main-nav');
function closeMenu() { menu.classList.remove('open'); menuToggle.setAttribute('aria-expanded', 'false'); menuToggle.setAttribute('aria-label', 'Abrir menu'); }
menuToggle.addEventListener('click', () => { const open = menuToggle.getAttribute('aria-expanded') !== 'true'; menu.classList.toggle('open', open); menuToggle.setAttribute('aria-expanded', String(open)); menuToggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu'); });
$$('a', menu).forEach(link => link.addEventListener('click', closeMenu));
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
document.addEventListener('click', event => { if (!event.target.closest('.header')) closeMenu(); });
window.matchMedia('(min-width:681px)').addEventListener('change', closeMenu);

const sectors = {
  escolas: { label: 'SECRETARIAS E SERVIÇOS ADMINISTRATIVOS', title: 'Mais organização na secretaria.\nMais tempo para a escola.', description: 'Requerimentos, correspondência e processos administrativos com um lugar definido e um percurso que pode acompanhar.', benefits: ['Organizar requerimentos e documentação administrativa', 'Acompanhar encaminhamentos entre responsáveis', 'Definir acessos de acordo com as funções'], folder: 'Secretaria escolar', document: 'Requerimento de declaração', category: 'Administrativo / Requerimentos', secondary: 'Correspondência recebida', cta: 'Quero conhecer para a minha escola', interest: 'Escolas' },
  empresas: { label: 'EQUIPAS E PROCESSOS DE TRABALHO', title: 'Menos informação dispersa.\nMais clareza na equipa.', description: 'Um contexto para organizar contratos, correspondência e documentação interna, com responsabilidades e percursos definidos.', benefits: ['Centralizar documentação de trabalho', 'Acompanhar o encaminhamento entre equipas', 'Organizar acessos por perfis e responsabilidades'], folder: 'Arquivo da empresa', document: 'Contrato de prestação de serviços', category: 'Empresa / Contratos', secondary: 'Correspondência comercial', cta: 'Quero conhecer para a minha empresa', interest: 'Empresas' },
  instituicoes: { label: 'ASSOCIAÇÕES E OUTRAS INSTITUIÇÕES', title: 'A informação fica.\nA organização acompanha.', description: 'Atas, comunicações e documentação institucional organizadas para os responsáveis de hoje e para a continuidade do trabalho.', benefits: ['Organizar atas e comunicações institucionais', 'Consultar o histórico dos documentos', 'Partilhar o trabalho com acessos definidos'], folder: 'Arquivo da instituição', document: 'Ata da reunião de direção', category: 'Instituição / Atas', secondary: 'Comunicação aos associados', cta: 'Quero conhecer para a minha instituição', interest: 'Instituições' },
  particulares: { label: 'ARQUIVO PESSOAL E ATIVIDADE INDEPENDENTE', title: 'O que é importante para si.\nNum arquivo com sentido.', description: 'Uma forma de pensar a organização de contratos, comprovativos e documentos pessoais, com um lugar para cada informação.', benefits: ['Organizar contratos e comprovativos', 'Encontrar documentos quando são necessários', 'Preparar o arquivo e a sua recuperação'], folder: 'O meu arquivo pessoal', document: 'Contrato de prestação de serviços', category: 'Arquivo pessoal / Contratos', secondary: 'Comprovativo de pagamento', cta: 'Quero conhecer para o meu arquivo', interest: 'Particulares' }
};
const sectorTabs = $$('[data-sector]');
function selectSector(key, focus = false) {
  const content = sectors[key]; if (!content) return;
  sectorTabs.forEach(button => { const selected = button.dataset.sector === key; button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1; if (selected && focus) button.focus(); });
  $('#sector-panel').setAttribute('aria-labelledby', 'tab-' + key);
  ['label', 'title', 'description', 'folder', 'document', 'category'].forEach(field => { $('#sector-' + field).textContent = content[field]; });
  $('#sector-document-secondary').textContent = content.secondary;
  $('#sector-benefits').replaceChildren(...content.benefits.map(text => { const li = document.createElement('li'); li.textContent = text; return li; }));
  const cta = $('.demo-request', $('#sector-panel'));
  cta.firstChild.textContent = content.cta + ' '; cta.dataset.interest = content.interest;
}
sectorTabs.forEach((button, index) => { button.addEventListener('click', () => selectSector(button.dataset.sector)); button.addEventListener('keydown', event => { let next; if (event.key === 'ArrowRight') next = (index + 1) % sectorTabs.length; if (event.key === 'ArrowLeft') next = (index + sectorTabs.length - 1) % sectorTabs.length; if (event.key === 'Home') next = 0; if (event.key === 'End') next = sectorTabs.length - 1; if (next !== undefined) { event.preventDefault(); selectSector(sectorTabs[next].dataset.sector, true); } }); });

let demoState = { stage: 1, title: '', category: '', destination: '' };
const steps = $$('[data-step]');
function showStep(stage, focus = true) {
  steps.forEach(button => { const number = Number(button.dataset.step); const active = number === stage; button.classList.toggle('active', active); if (active) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current'); button.disabled = number > demoState.stage; });
  $$('.demo-stage').forEach(view => { view.hidden = view.id !== 'demo-stage-' + stage; });
  const heading = $('#demo-stage-' + stage + ' h3'); heading.tabIndex = -1;
  if (focus) heading.focus({ preventScroll: true });
}
steps.forEach(button => button.addEventListener('click', () => { if (Number(button.dataset.step) <= demoState.stage) showStep(Number(button.dataset.step)); }));
$('#register-demo').addEventListener('submit', event => {
  event.preventDefault();
  const title = $('#demo-title').value.trim(); if (title.length < 3) { $('#demo-title').setCustomValidity('Indique pelo menos três caracteres para o nome.'); $('#demo-title').reportValidity(); return; }
  demoState = { stage: 2, title, category: $('#demo-category').value, destination: '' };
  $$('.current-document-title').forEach(node => { node.textContent = demoState.title; });
  $$('.current-document-category').forEach(node => { node.textContent = demoState.category; });
  $('#demo-live').textContent = 'Exemplo registado. Escolha agora o destino do documento.';
  showStep(2);
});
$('#demo-title').addEventListener('input', event => event.target.setCustomValidity(''));
$('#forward-demo').addEventListener('submit', event => { event.preventDefault(); if (demoState.stage < 2) return; demoState.stage = 3; demoState.destination = $('#demo-destination').value; $('#current-destination').textContent = demoState.destination; $('#demo-live').textContent = 'Exemplo encaminhado. O histórico da simulação está disponível.'; showStep(3); });
$('#demo-reset').addEventListener('click', () => { demoState = { stage: 1, title: '', category: '', destination: '' }; $('#register-demo').reset(); $('#forward-demo').reset(); $('#demo-title').setCustomValidity(''); $('#demo-live').textContent = 'Demonstração reiniciada.'; showStep(1); });

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
$('#year').textContent = String(new Date().getFullYear());

// Enable interactive controls only after their handlers have been registered.
document.body.classList.remove('no-js');

})();

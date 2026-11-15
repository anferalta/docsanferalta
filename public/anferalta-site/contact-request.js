export const CONTACT_EMAIL = 'suporte@anferalta.com';
export const CONTACT_SUBJECT = 'Pedido de demonstração — AnferaltaDocs';
export const CONTEXTS = ['Escolas', 'Empresas', 'Instituições', 'Particulares', 'Outro contexto'];

export function buildDemoRequest(input) {
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

export function buildMailtoUrl(request) {
  // Recipient and subject are fixed; visitor data is encoded only into the body.
  return 'mailto:' + CONTACT_EMAIL + '?subject=' + encodeURIComponent(CONTACT_SUBJECT) + '&body=' + encodeURIComponent(request.body);
}

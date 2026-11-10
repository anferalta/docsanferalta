# Validação do servidor local

O virtual host WAMP foi preparado para servir apenas `public/`, sem aliases para documentos, backups, configuração ou código interno. HTTP redireciona para `https://anferalta.local/`. O certificado local cobre `anferalta.local` e `anferaltadocs.local`, foi assinado pela CA de desenvolvimento já existente no computador e não foi instalado um novo certificado raiz.

As chaves privadas ficam em `secrets/tls/`, com acesso restrito. Não são distribuídas no pacote. O certificado usa RSA 3072, SHA-256, validade de um ano, uso servidor e não permite atuar como CA. TLS 1.0/1.1 foram desativados; TLS 1.2/1.3 estão permitidos. É um certificado de desenvolvimento, não um certificado para domínios de clientes.

O helper local `scripts/aplicar_servidor_local.ps1` exige elevação do Windows, guarda a configuração anterior, executa `httpd -t`, reinicia apenas `wampapache64` e valida o servidor. O MySQL não é reiniciado. O diagnóstico encontra-se em `storage/temp/server-validation-result.json`.

```powershell
./scripts/validar_servidor.ps1
```

Foram verificadas 16 condições por pedidos reais: login, cadeia/identidade/validade TLS, cookies Secure/HttpOnly/SameSite, cabeçalhos de proteção, redirecionamento HTTP, CSS, documento anónimo, rejeição de login sem CSRF e bloqueio de `.env`, Git, vendor, storage, backups, segredos e scripts de diagnóstico. O validador não usa callback para ignorar certificados. A consulta de revogação é desativada para a CA de desenvolvimento, que não publica CRL/OCSP; as restantes verificações TLS mantêm-se.

Para um servidor de cliente, executar o validador com a URL real e configurar um certificado do domínio confiável nos computadores da entidade, com o processo de renovação apropriado. Validar também rede, firewall, contas de serviço, conta de BD com privilégios mínimos e cópias externas de backup. A validação local não comprova a infraestrutura de outro servidor.

As migrações e o restauro só usam manutenção por CLI. Pedidos novos recebem 503 enquanto há uma operação exclusiva. Sessões usam a pasta privada do projeto, rodam o identificador no login e não confiam automaticamente em `X-Forwarded-Proto`; para terminação TLS num proxy, configurar explicitamente `SESSION_COOKIE_SECURE=true` e proteger a ligação interna conforme a arquitetura da entidade.

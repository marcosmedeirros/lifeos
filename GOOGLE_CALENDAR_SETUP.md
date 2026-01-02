# Configuração do Google Calendar no LifeOS

## 1. Criar Projeto no Google Cloud Console

1. Acesse: https://console.cloud.google.com
2. Crie um novo projeto ou selecione um existente
3. Nome sugerido: "LifeOS Calendar Integration"

## 2. Ativar Google Calendar API

1. No menu lateral, vá em **APIs e Serviços** > **Biblioteca**
2. Busque por "Google Calendar API"
3. Clique em **Ativar**

## 3. Criar Credenciais OAuth 2.0

1. No menu lateral, vá em **APIs e Serviços** > **Credenciais**
2. Clique em **+ Criar Credenciais** > **ID do cliente OAuth**
3. Se solicitado, configure a **Tela de consentimento OAuth**:
   - Tipo: **Externo**
   - Nome do app: **LifeOS**
   - Email de suporte: seu email
   - Escopos: adicione `../auth/calendar` (Google Calendar API)
   - Usuários de teste: adicione seu email do Google

4. Volte para **Credenciais** > **+ Criar Credenciais** > **ID do cliente OAuth**
5. Tipo de aplicativo: **Aplicativo da Web**
6. Nome: **LifeOS Web Client**
7. **URIs de redirecionamento autorizados**:
   - Desenvolvimento: `http://localhost/lifeos/modules/google_agenda.php?callback=1`
   - Produção: `https://seudominio.com/lifeos/modules/google_agenda.php?callback=1`

8. Clique em **Criar**
9. Copie o **ID do cliente** e **Chave secreta do cliente**

## 4. Configurar no LifeOS

### Opção A: Usando arquivo .env (recomendado)

Crie/edite o arquivo `.env` na raiz do LifeOS:

```env
GOOGLE_CLIENT_ID=seu_client_id_aqui.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=sua_client_secret_aqui
```

### Opção B: Usando config.php

Edite `config.php` e adicione:

```php
putenv("GOOGLE_CLIENT_ID=seu_client_id_aqui.apps.googleusercontent.com");
putenv("GOOGLE_CLIENT_SECRET=sua_client_secret_aqui");
```

## 5. Executar SQL de Configuração

Execute o script `setup_google_calendar.sql` no seu banco de dados MySQL:

```bash
mysql -u seu_usuario -p seu_banco < setup_google_calendar.sql
```

Ou pelo phpMyAdmin:
1. Acesse phpMyAdmin
2. Selecione seu banco de dados
3. Vá na aba SQL
4. Cole o conteúdo de `setup_google_calendar.sql`
5. Clique em Executar

## 6. Testar Integração

1. Acesse o LifeOS
2. No menu lateral, clique em **Google Agenda**
3. Clique em **Conectar com Google**
4. Faça login com sua conta Google
5. Autorize o LifeOS a acessar seu Google Calendar
6. Clique em **Sincronizar do Google** para importar eventos

## Funcionalidades

✅ **Sincronização bidirecional**: eventos criados em qualquer lugar aparecem em ambos
✅ **OAuth2 seguro**: autenticação padrão Google
✅ **Renovação automática**: tokens renovados automaticamente
✅ **Criar eventos**: crie no LifeOS e sincronize com Google Calendar
✅ **Importar eventos**: busque eventos dos últimos 30 dias e próximos 90 dias

## Solução de Problemas

### Erro "Credenciais não configuradas"
- Verifique se GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET estão definidos
- Reinicie o servidor Apache/PHP

### Erro "redirect_uri_mismatch"
- Verifique se a URI de redirecionamento no Google Cloud Console corresponde exatamente à URL do seu site
- Exemplo: `http://localhost/lifeos/modules/google_agenda.php?callback=1`

### Eventos não sincronizam
- Clique em "Sincronizar do Google" manualmente
- Verifique permissões na conta Google
- Verifique logs de erro do PHP

## Segurança

🔒 **Tokens seguros**: armazenados criptografados no banco
🔒 **OAuth2**: padrão de autenticação do Google
🔒 **Renovação automática**: tokens renovados antes de expirar
🔒 **Desconexão**: possibilidade de revogar acesso a qualquer momento

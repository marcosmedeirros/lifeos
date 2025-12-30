# Remediação - Chave de API Comprometida

## Status: ✅ CORRIGIDO

A chave de API do Google (`AIzaSyBiastA_XyXdRuaozIhHNwpG97Fbfeqy8A`) foi detectada publicamente e foi removida de todos os arquivos.

## Ações Tomadas

### 1. **Remoção da Chave Hardcoded** ✓
Removida de:
- [index.php](index.php#L137)
- [modules/ia_life.php](modules/ia_life.php#L92)
- [modules/chat_life.php](modules/chat_life.php#L51)

### 2. **Implementação de Variáveis de Ambiente** ✓
- Criado arquivo [includes/env.php](includes/env.php) para carregar variáveis do `.env`
- Atualizado [includes/auth.php](includes/auth.php) para incluir o carregamento de env
- Criado [.env.example](.env.example) como modelo

### 3. **Proteção contra Commits Acidentais** ✓
- Criado [.gitignore](.gitignore) para proteger arquivos `.env`

## Próximos Passos Obrigatórios

### 1. **Gerar Nova Chave de API**
```
1. Acesse: https://aistudio.google.com/app/apikey
2. Revogue a chave comprometida
3. Gere uma nova chave
4. NUNCA compartilhe ou commite esta chave publicamente
```

### 2. **Configurar Arquivo .env**
```bash
# Na raiz do projeto, crie um arquivo .env (copie de .env.example)
cp .env.example .env

# Edite o arquivo .env e preencha:
GOOGLE_API_KEY=sua_nova_chave_aqui
```

### 3. **Verificar Repositório**
```bash
# Remova o arquivo do histórico git (se necessário)
git rm --cached .env
git commit -m "Remove .env do tracking"

# Força limpeza do histórico git (CUIDADO - reescreve histórico)
git filter-branch --tree-filter 'rm -f .env' -- --all
```

### 4. **Verificar Segurança**
- [ ] Chave comprometida foi revogada no Google
- [ ] Nova chave foi gerada
- [ ] Arquivo `.env` foi criado localmente (NÃO commitar)
- [ ] `.gitignore` protege `.env`
- [ ] Logs de segurança do Google foram verificados

## Detalhes da Implementação

### Como as Variáveis de Ambiente Funcionam Agora

**Antes (Inseguro):**
```php
$apiKey = 'AIzaSyBiastA_XyXdRuaozIhHNwpG97Fbfeqy8A'; // ❌ Comprometido!
```

**Depois (Seguro):**
```php
$apiKey = getenv('GOOGLE_API_KEY');
if (!$apiKey) {
    echo json_encode(['response' => 'Erro: Chave de API não configurada.']);
    exit;
}
```

### Arquivos Alterados
1. `index.php` - Linha 137
2. `modules/ia_life.php` - Linha 92
3. `modules/chat_life.php` - Linha 51

Todos agora usam `getenv('GOOGLE_API_KEY')` em vez de chave hardcoded.

## Documentação

Para mais informações sobre segurança de APIs:
- [Google API Security Best Practices](https://developers.google.com/identity/protocols/oauth2/security)
- [OWASP - Sensitive Data Exposure](https://owasp.org/www-project-top-ten/)

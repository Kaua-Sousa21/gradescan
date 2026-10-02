# Gabarito Online 4.0

Plataforma web para criação, impressão, escaneamento e correção de provas objetivas. Desenvolvida em **PHP 8.1+**, **MySQL/MariaDB**, HTML, CSS e JavaScript, com scanner diretamente no navegador do celular.

A versão 4.0 prioriza confiabilidade: quando a leitura não atinge um nível seguro de confiança, o sistema **não tenta adivinhar**. A questão é enviada para revisão manual ou a captura é rejeitada.

## Principais recursos

### Administração

- cadastro de administradores e professores;
- cadastro de alunos;
- criação e gerenciamento de turmas;
- vínculo de professores, disciplinas e alunos;
- acesso a todas as provas e resultados;
- auditoria de ações;
- backup SQL pelo painel;
- diagnóstico de servidor e dependências do scanner.

### Professor

- acesso apenas às turmas vinculadas;
- criação de provas com alternativas A–E;
- gabarito oficial e pontuação por questão;
- geração de gabaritos personalizados por aluno;
- identificação automática por QR;
- scanner em tela cheia dentro do navegador;
- correção de rotação e perspectiva;
- prévia do alinhamento antes da leitura;
- revisão de questões duvidosas;
- resultado imediato por aluno e tabela da turma.

## Scanner 4.0

O scanner utiliza OpenCV.js e jsQR no navegador. A leitura da versão 4.0 possui:

- detecção dos marcadores do bloco do gabarito;
- suporte a pequenas inclinações da câmera;
- correção de perspectiva e rotação 0°, 90°, 180° e 270°;
- contorno de alinhamento em tempo real;
- prévia do documento retificado antes da correção;
- validação de **geometria, grade, perspectiva e nitidez**;
- calibração independente das colunas esquerda e direita;
- medição da mesma bolha em vários pontos para reduzir ruído;
- limiar adaptativo calculado a partir da própria foto;
- comparação entre as cinco alternativas da mesma questão;
- detecção conservadora de dupla marcação;
- questões de baixa confiança obrigatoriamente marcadas como **Revisar**;
- rejeição automática de capturas com muitas respostas incertas.

> Nenhum scanner por câmera pode garantir 100% de acerto em todas as condições de iluminação, foco, impressão e preenchimento. O objetivo desta versão é reduzir erros usando uma abordagem conservadora: **na dúvida, o sistema pede revisão ou uma nova foto em vez de salvar uma resposta estimada**.

Mais detalhes: [`docs/SCANNER.md`](docs/SCANNER.md).

## Gabarito impresso

O formulário 4.0 é um bloco independente e escalável. Ele contém:

- 6 marcadores pretos de alinhamento;
- QR de identificação;
- nome, matrícula e turma;
- prova, disciplina e versão;
- alternativas A–E.

O QR novo usa o formato compacto `GO4`, que inclui prova, aluno e um prefixo do token individual. O backend valida esse código antes de considerar a identificação automática. Gabaritos antigos `GO2`, `GS1` e `GS2` continuam compatíveis.

Ao redimensionar o gabarito para colocá-lo dentro da própria folha da prova, mantenha sempre a **proporção original** e os **6 marcadores totalmente visíveis**.

## Requisitos

- PHP 8.1 ou superior;
- extensão PDO MySQL;
- MySQL 5.7+ ou MariaDB equivalente;
- HTTPS em produção para acesso à câmera;
- navegador moderno no celular;
- internet no dispositivo para carregar OpenCV.js/jsQR/QRCode.js pelas fontes configuradas no projeto.

## Instalação

1. Crie um banco MySQL/MariaDB.
2. Envie os arquivos para a hospedagem.
3. Certifique-se de que o domínio/subdomínio usa HTTPS.
4. Acesse `setup.php`.
5. Informe banco, nome da escola e primeiro administrador.
6. Conclua a instalação.
7. Entre no sistema e abra **Administração → Diagnóstico**.
8. Gere uma prova de teste e imprima um gabarito novo antes do uso oficial.

O instalador cria os arquivos locais de configuração. Eles estão incluídos no `.gitignore` e **não devem ser enviados para o GitHub**.

## Uso em localhost

Exemplo com XAMPP/WAMP:

1. coloque a pasta em `htdocs/gabarito-online`;
2. crie um banco vazio;
3. acesse `http://localhost/gabarito-online/setup.php`;
4. conclua a instalação pelo navegador.

A câmera via navegador normalmente exige HTTPS fora de `localhost`.

## Publicar no GitHub

O ZIP desta versão já está organizado como raiz de repositório.

```bash
git init
git add .
git commit -m "Gabarito Online 4.0"
git branch -M main
git remote add origin https://github.com/SEU-USUARIO/SEU-REPOSITORIO.git
git push -u origin main
```

Antes do `git add .`, confira que estes arquivos **não existem no commit**:

```text
config/app.local.php
config/database.local.php
```

O projeto possui GitHub Actions em `.github/workflows/ci.yml` para executar lint de PHP e validação sintática do JavaScript a cada push e pull request.

## Estrutura

```text
admin/          administração, auditoria, backup e diagnóstico
api/            endpoints usados pela interface
assets/         CSS e JavaScript
classes/        turmas e vínculos
config/         configurações e exemplos locais
core/           autenticação, CSRF, segurança e helpers
database/       schema MySQL
docs/           documentação técnica
exams/          provas, impressão, scanner e resultados
partials/       componentes compartilhados
teacher/        dashboard do professor
setup.php       instalador inicial
```

## Segurança

Entre as proteções existentes:

- `password_hash()` e `password_verify()`;
- PDO prepared statements;
- CSRF em operações de escrita;
- sessões com `HttpOnly`, `SameSite` e `Secure` em HTTPS;
- regeneração e expiração de sessão;
- controle de acesso por perfil;
- limite de tentativas de login;
- auditoria de operações;
- CSP, HSTS, `X-Content-Type-Options` e `Permissions-Policy`;
- confirmação antes de substituir uma correção existente;
- nota recalculada no servidor com o gabarito oficial;
- validação do aluno/turma/prova no backend;
- validação do prefixo de token dos novos QR `GO4`.

Veja também [`SECURITY.md`](SECURITY.md).

## Testes antes do uso oficial

Teste com diferentes:

- celulares;
- condições de iluminação;
- inclinações moderadas;
- tamanhos de impressão;
- canetas azul e preta;
- preenchimentos mais fortes e mais leves.

O scanner foi configurado para rejeitar leituras inseguras, mas a homologação com as impressoras, papéis e aparelhos usados pela escola continua sendo importante.

## Versão

**4.0.0** — consulte [`CHANGELOG.md`](CHANGELOG.md).

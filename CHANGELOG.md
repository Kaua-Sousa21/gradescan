# Changelog

## 4.0.0

- leitura de bolhas com limiar adaptativo aprendido da própria captura;
- calibração independente das colunas esquerda e direita;
- amostragem múltipla no interior de cada bolha para reduzir ruído;
- validação de nitidez antes da correção;
- confirmação de alinhamento mais conservadora;
- respostas de baixa confiança passam obrigatoriamente por revisão;
- detecção mais rígida de dupla marcação;
- scanner rejeita capturas com muitas respostas duvidosas em vez de estimar a nota;
- QR `GO4` com prefixo de token para validar aluno/prova no servidor;
- compatibilidade mantida com gabaritos `GO2`, `GS1` e `GS2` anteriores;
- identidade visual pública atualizada para **Gabarito Online**;
- projeto organizado para GitHub com `.gitignore`, documentação e workflow de lint.

## 3.1

- prévia do alinhamento antes de ler respostas;
- contorno geométrico em tempo real;
- scanner em tela cheia no navegador;
- correção de perspectiva e rotação.

## 2.9

- gabarito transformado em bloco escalável com seis marcadores próprios;
- suporte a diferentes tamanhos de impressão mantendo a proporção.

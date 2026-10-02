# Scanner — funcionamento da versão 4.0

O scanner foi projetado para preferir **rejeitar uma captura incerta** em vez de registrar uma alternativa errada.

## Fluxo

1. A câmera encontra os marcadores do bloco.
2. A geometria e a estabilidade do enquadramento são verificadas.
3. A perspectiva e a rotação são corrigidas.
4. A nitidez e a grade das bolhas são validadas.
5. O usuário confere a prévia retificada.
6. O QR identifica aluno/prova.
7. A posição das bolhas é calibrada separadamente nas duas colunas.
8. O sistema aprende o nível visual das bolhas vazias da própria captura.
9. Cada alternativa é medida em múltiplos pontos e comparada com as outras quatro da questão.
10. Marcações duplas, fracas ou de baixa confiança ficam como **Revisar**.
11. Se a quantidade de questões duvidosas for alta, a captura é rejeitada.
12. O servidor recalcula a nota usando o gabarito oficial.

## Boas práticas de impressão

- mantenha a proporção do bloco;
- não corte os seis marcadores;
- evite reduzir demais o gabarito;
- use impressão nítida em preto;
- preencha completamente uma única alternativa.

## Boas práticas de captura

- use iluminação uniforme;
- evite reflexos e sombras fortes;
- mantenha todos os marcadores visíveis;
- aguarde o foco da câmera;
- pequenas inclinações são corrigidas, mas ângulos extremos devem ser evitados.

Nenhum sistema OMR baseado em câmera pode garantir 100% de acerto em qualquer condição física. Por isso a versão 4.0 usa validações conservadoras e revisão manual quando a confiança não é suficiente.

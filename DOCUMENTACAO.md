# Documentação funcional do RodaQuadra

Este documento explica as regras e a operação do RodaQuadra. Ele descreve o comportamento atualmente implementado para encontros de vôlei, futsal e futebol.

Para instalação, desenvolvimento e deploy, consulte o [README](README.md).

## Visão geral

O RodaQuadra ajuda uma pessoa organizadora a controlar um encontro esportivo do início ao fim:

1. cria o encontro e define a modalidade;
2. registra os participantes na ordem em que chegam;
3. forma e equilibra os times automaticamente;
4. inicia as partidas e controla placar e cronômetro;
5. movimenta os times na fila após cada resultado;
6. registra saídas, substituições e empréstimos;
7. mantém histórico, ranking e um placar público.

Cada conta enxerga somente seus próprios jogadores e encontros. Apenas um encontro pode permanecer ativo por conta; para iniciar outro, o atual deve ser encerrado.

## Conceitos importantes

| Conceito | Significado |
| --- | --- |
| Encontro | Uma sessão esportiva realizada em uma data, contendo participantes, times e partidas |
| Jogador | Pessoa cadastrada permanentemente na conta do organizador |
| Participante | Presença de um jogador em um encontro específico |
| Time em quadra | Time que participa da partida atual |
| Time na fila | Time completo ou parcial aguardando sua vez |
| Pessoa disponível | Participante presente que ainda não pertence a um time |
| Partida confirmada | Resultado definitivo que já entrou no histórico e no ranking |
| Escalação histórica | Cópia dos jogadores de cada lado no momento em que a partida começou |

## Criando um encontro

Na tela inicial do painel, escolha:

- a modalidade: vôlei, futsal ou futebol;
- a quantidade de jogadores por time, entre 2 e 11.

Os atalhos exibem formatos comuns. Ao trocar a modalidade, o sistema sugere:

- vôlei: 4×4;
- futsal: 5×5;
- futebol: 11×11.

A sugestão pode ser alterada antes da criação do encontro.

### Configurações do encontro

Enquanto o encontro estiver ativo, podem ser configurados:

| Configuração | Valores | Efeito |
| --- | --- | --- |
| Modo dos times | Dinâmico ou fixo | Define se jogadores podem ser reorganizados automaticamente entre rodadas |
| Peso de gênero | 0 a 100 | Importância da diferença de gênero no equilíbrio |
| Peso de habilidade | 0 a 100 | Importância da diferença de nível no equilíbrio |
| Desempate do ranking | Aproveitamento ou saldo | Segundo critério usado depois do número de vitórias |
| Meta do vôlei | 5 a 30 pontos | Pontuação normal necessária para vencer |
| Duração | 1 a 120 minutos, ou livre | Habilita o cronômetro da partida |

Pelo menos um dos pesos de equilíbrio deve ser maior que zero. Alterações de meta e duração valem para partidas criadas depois da mudança; cada partida guarda sua própria configuração.

## Cadastro e ordem de chegada

Cada jogador possui:

- nome;
- gênero;
- nível de habilidade de 1 a 5.

A escala de habilidade considera números maiores como maior nível técnico:

| Nível | Classificação |
| --- | --- |
| 1 | Iniciante |
| 2 | Básico |
| 3 | Intermediário |
| 4 | Avançado |
| 5 | Muito avançado |

O nível não limita as ações do jogador. Ele é usado no cálculo de equilíbrio: o sistema soma os níveis dos participantes de cada time e tenta reduzir a diferença entre os totais.

É possível cadastrar uma pessoa nova ou selecionar alguém que já participou de outro encontro da mesma conta.

Ao entrar no encontro, cada participante recebe uma posição crescente na lista de chegada. Essa ordem tem prioridade sobre o equilíbrio:

- o sistema nunca pula uma pessoa mais antiga para escolher outra que chegou depois apenas para melhorar os times;
- novos participantes entram no fim da lista;
- uma pessoa que saiu e depois retorna recebe uma nova posição no fim da lista;
- uma pessoa já presente não pode ser adicionada novamente.

## Formação automática dos times

Quando há pessoas disponíveis suficientes para completar um time, o sistema cria o próximo time usando as primeiras pessoas da lista de chegada que ainda não possuem equipe.

### Primeira partida

Para a primeira partida são necessárias duas equipes completas. Quando o segundo time fica completo, o sistema reúne as primeiras `2 × tamanho do time` pessoas e procura a melhor divisão possível entre os dois lados.

Exemplo para 4×4:

1. as posições 1 a 8 são selecionadas;
2. ninguém depois da posição 8 pode substituir uma dessas pessoas;
3. as oito pessoas são distribuídas entre Time 1 e Time 2;
4. o sistema minimiza a diferença ponderada de gênero e habilidade;
5. a partida começa automaticamente.

### Cálculo do equilíbrio

O sistema compara as possíveis divisões do grupo selecionado e calcula:

```text
pontuação do desequilíbrio =
    diferença na quantidade de mulheres × peso de gênero
    + diferença no nível total × peso de habilidade
```

A divisão com menor pontuação é escolhida. O painel mostra, para os times em quadra:

- quantidade de mulheres em cada lado;
- soma dos níveis de habilidade;
- diferença de gênero;
- diferença de habilidade.

Se duas divisões tiverem o mesmo resultado, o sistema usa uma ordem determinística baseada na seleção original. Isso evita mudanças aleatórias ao repetir a operação.

### Equilíbrio de times futuros

Dois times completos e ainda inéditos podem ser reequilibrados quando forem entrar em quadra. O sistema só aplica a mudança se ela realmente reduzir a pontuação de desequilíbrio.

Não ocorre reequilíbrio automático quando:

- algum dos times já disputou uma partida;
- algum time está incompleto;
- o encontro usa o modo fixo;
- a partida foi explicitamente autorizada como incompleta.

Antes de o primeiro ponto ou gol ser registrado, a pessoa organizadora também pode trocar manualmente um jogador de cada time em quadra. Depois que o placar começa ou outra alteração de escalação é registrada, a troca fica bloqueada.

## Modos de time

### Modo dinâmico

O modo dinâmico prioriza a circulação das pessoas. Entre as partidas, o sistema pode:

- criar um novo time com pessoas que estavam sem equipe;
- completar o desafiante com jogadores do time perdedor;
- registrar essas movimentações no histórico;
- oferecer a reorganização da próxima partida quando novas pessoas chegam antes de o placar começar.

Ao completar um time com jogadores do perdedor, a ordem de chegada é respeitada. O time perdedor nunca é reduzido a zero: pelo menos uma pessoa permanece nele.

### Modo fixo

O modo fixo preserva a composição das equipes. O sistema continua controlando fila, placar, permanência em quadra e ranking, mas não redistribui automaticamente jogadores entre os times ao avançar uma rodada.

Saídas, preenchimento de vagas e empréstimos confirmados continuam disponíveis.

## Regras da fila

Dois times jogam por vez. Os demais ficam ordenados por posição na fila.

Ao confirmar uma partida:

1. o vencedor e o perdedor recebem mais uma partida consecutiva;
2. o perdedor vai para o fim da fila;
3. normalmente, o vencedor permanece em quadra;
4. o primeiro time que aguardava entra como desafiante.

### Limite de duas partidas consecutivas

Quando o vencedor completa duas partidas consecutivas, há duas possibilidades:

- se existirem pelo menos dois times esperando fora da quadra, vencedor e perdedor saem, e os dois primeiros da fila entram;
- se não existirem dois times esperando, o vencedor pode continuar jogando.

Quando os dois times saem, o vencedor é colocado no fim da fila antes do perdedor. Os times que já estavam esperando mantêm sua prioridade.

Sempre que um time volta para a fila, seu contador de partidas consecutivas é zerado.

## Placar e definição do vencedor

O placar possui botões para adicionar e retirar um ponto ou gol. Valores negativos não são permitidos. O primeiro acréscimo também marca o início efetivo do placar.

O resultado só altera a fila depois da confirmação no Dialog. Até esse momento, os pontos podem ser corrigidos.

### Vôlei

A meta padrão é 15 pontos e pode ser configurada entre 5 e 30.

Sem empate próximo à meta, vence quem chega exatamente à meta enquanto o adversário possui no máximo `meta − 2` pontos. Com meta 15, exemplos válidos são 15×0 até 15×13.

Quando os dois times chegam a pelo menos `meta − 1`, inicia-se a regra de desempate do sistema: vence quem chega exatamente a `meta + 2`, desde que o adversário não ultrapasse `meta + 1`.

Com meta 15, os resultados válidos de desempate são:

- 17×14;
- 17×15;
- 17×16;
- ou os mesmos placares com os lados invertidos.

Depois que um placar vencedor é alcançado, novos pontos ficam bloqueados. Ainda é possível retirar pontos para corrigir o resultado antes de confirmá-lo.

### Futsal e futebol

Em futsal e futebol, vence o time com mais gols. Não existe uma meta obrigatória de gols.

Se o placar estiver empatado, a pessoa organizadora deve indicar manualmente qual time venceu o desempate antes de confirmar. Essa escolha define o vencedor sem alterar o placar registrado.

## Cronômetro

Quando a duração da partida é configurada, o cronômetro pode ser:

- iniciado;
- pausado;
- retomado;
- zerado antes do início do placar.

Ao registrar o primeiro ponto ou gol, o cronômetro começa automaticamente caso ainda esteja zerado e parado.

Chegar ao tempo previsto exibe um aviso, mas não confirma nem encerra a partida automaticamente. A pessoa organizadora ainda precisa definir um vencedor válido e confirmar o resultado.

Ao confirmar a partida, o tempo decorrido é salvo e o cronômetro é interrompido.

## Times incompletos

Uma partida não começa automaticamente com time vazio ou incompleto.

Quando a próxima equipe não possui jogadores suficientes, a pessoa organizadora pode:

- aguardar novas chegadas;
- preencher a vaga com uma pessoa disponível;
- confirmar um empréstimo sugerido;
- autorizar explicitamente que a próxima partida comece incompleta.

Um time vazio nunca pode entrar, mesmo com autorização. A confirmação de partida incompleta vale para a partida que será criada e fica registrada nela.

## Saídas, substituições e empréstimos

### Saída de participante

Ao marcar que uma pessoa saiu:

1. um Dialog pede confirmação;
2. a pessoa passa ao estado “foi embora” e deixa de ser elegível;
3. a saída é registrada no histórico;
4. o sistema tenta preencher automaticamente a vaga.

As vagas são verificadas nesta ordem:

1. times em quadra;
2. times na fila, pela posição;
3. desempate pelo identificador do time.

Para cada vaga, entra a primeira pessoa presente e sem time, respeitando a ordem de chegada.

Se a equipe estiver em quadra, a escalação histórica da partida atual é atualizada com o substituto. O participante que saiu deixa de aparecer entre os jogadores ativos, mas sua participação anterior continua preservada no histórico.

### Empréstimo

O empréstimo só é sugerido quando:

- existe um time incompleto;
- não há pessoa presente e sem time disponível;
- há outro time completo aguardando na fila;
- o candidato pertence a esse outro time e é o primeiro elegível pela ordem de chegada.

O painel mostra o impacto de gênero e habilidade antes da confirmação. O empréstimo exige um Dialog porque deixará o time de origem incompleto. Se a situação da fila mudar antes da confirmação, a operação é rejeitada e uma nova sugestão deve ser consultada.

## Pessoas aguardando e reorganização da próxima partida

No modo dinâmico, pode surgir um grupo parcial de pessoas sem time logo após uma rodada. Antes de começar o placar seguinte, o painel pode oferecer a ação de colocá-las em quadra.

Essa reorganização só é permitida quando:

- existe uma partida anterior confirmada e uma nova partida ainda zerada;
- o vencedor anterior está enfrentando o perdedor anterior;
- há entre 1 e `tamanho do time − 1` pessoas sem equipe;
- não existe outro time esperando;
- nenhuma mudança de escalação já foi feita na partida atual.

O sistema cria um time com essas pessoas, completa as vagas com jogadores do perdedor e substitui o desafiante. Se ainda não for possível completar o novo time, a operação é recusada.

## Confirmações com Dialog

Ações que alteram o estado importante do encontro usam o componente Dialog do TallStackUI:

- encerrar encontro;
- confirmar resultado;
- definir vencedor de desempate;
- autorizar próxima partida incompleta;
- desfazer resultado;
- registrar saída;
- confirmar empréstimo.

Abrir o Dialog não executa a operação. A mudança ocorre somente ao pressionar o botão de confirmação. Cancelar ou fechar preserva o estado atual.

## Desfazer o último resultado

Ao confirmar uma partida, o sistema salva uma fotografia da fila e dos contadores. A ação “Desfazer último” restaura:

- o resultado anterior ao estado de partida em andamento;
- vencedor e horário de confirmação;
- posições dos times na fila;
- contadores de partidas consecutivas;
- formações e movimentações automáticas da rodada seguinte.

O desfazer só está disponível enquanto a partida seguinte ainda estiver em 0×0, sem placar iniciado.

A operação é bloqueada se, depois da confirmação:

- o placar seguinte já foi alterado;
- houve saída, empréstimo, troca ou outra mudança não automática na escalação;
- a estrutura dos times mudou de uma forma que não pode ser restaurada com segurança;
- o encontro foi encerrado.

## Histórico de partidas

Somente partidas confirmadas entram no histórico e no ranking.

Cada registro guarda:

- sequência da partida;
- times envolvidos;
- placar final;
- vencedor;
- escalação de cada lado naquele momento;
- duração efetiva, quando aplicável;
- data e hora da confirmação;
- indicação de autorização para equipe incompleta.

A escalação é armazenada como uma fotografia. Portanto, trocas feitas posteriormente não reescrevem quem participou de partidas antigas.

## Ranking

Para cada time, o ranking calcula:

- jogos disputados;
- vitórias;
- derrotas;
- aproveitamento;
- pontos ou gols marcados;
- pontos ou gols sofridos;
- saldo.

A ordenação sempre começa pelo maior número de vitórias.

Se o desempate escolhido for **aproveitamento**, a ordem será:

1. vitórias;
2. aproveitamento;
3. saldo;
4. time criado primeiro.

Se o desempate escolhido for **saldo**, a ordem será:

1. vitórias;
2. saldo;
3. aproveitamento;
4. time criado primeiro.

O aproveitamento é calculado como `vitórias ÷ jogos disputados`. Times sem partidas possuem aproveitamento zero.

### Jogos por pessoa

O painel também conta quantas partidas cada pessoa disputou no encontro. A contagem usa as escalações históricas dos dois lados e considera somente partidas confirmadas.

- Cada pessoa conta no máximo uma vez por partida, mesmo que seu nome apareça mais de uma vez na escalação.
- A partida atual não entra na contagem antes da confirmação.
- Pessoas que ainda não disputaram uma partida aparecem com `0 jogos`.
- Ao desfazer o último resultado, a partida deixa de ser confirmada e sai automaticamente da contagem.
- A contagem pertence ao encontro atual; ela não soma partidas de encontros anteriores.

O total aparece na lista de chegada e na seção “Jogos por pessoa”, junto ao histórico e ranking. Não existe uma coluna acumulada no cadastro do jogador: o valor é calculado diretamente do histórico para permanecer coerente com confirmações e desfazimentos.

## Placar público

Cada encontro recebe um token aleatório de 40 caracteres. O link de placar público pode ser compartilhado com jogadores e espectadores sem liberar acesso ao painel administrativo.

O placar público mostra:

- modalidade e formato;
- partida atual ou último resultado;
- times, jogadores e placar;
- cronômetro, quando configurado;
- próximo time;
- pessoas aguardando a formação de equipe;
- estado de encontro encerrado.

A página consulta atualizações periodicamente. Ela é somente leitura: pontuação, fila e participantes continuam sendo controlados no painel autenticado.

O token não aparece na serialização normal do modelo e não segue o identificador numérico do encontro, reduzindo a possibilidade de adivinhação do endereço.

## Encerramento do encontro

Encerrar um encontro exige confirmação. Ao confirmar:

- o encontro passa ao estado encerrado;
- uma partida ainda em andamento é cancelada;
- resultados já confirmados permanecem no histórico e no ranking;
- cadastro de chegadas, alterações no placar, configurações e desfazer ficam bloqueados;
- o placar público continua exibindo o último resultado disponível.

Depois disso, a conta pode iniciar um novo encontro.

## Registro de alterações

O sistema mantém um histórico separado para movimentações de jogadores:

| Tipo | Quando é registrado |
| --- | --- |
| Saída | Uma pessoa deixa o encontro |
| Retorno | Uma pessoa volta e entra no fim da lista |
| Substituição | Uma pessoa disponível preenche uma vaga |
| Empréstimo | Uma pessoa sai de um time completo na fila para completar outro |
| Formação | Pessoas aguardando formam um novo time durante a rotação |
| Rotação | Jogadores do perdedor completam um novo desafiante no modo dinâmico |
| Troca | A pessoa organizadora troca jogadores entre os lados antes do placar |
| Equilíbrio | O sistema melhora automaticamente dois times inéditos |

Esses registros permitem explicar por que a composição atual difere da formação original.

## Regras de segurança e consistência

O sistema aplica as seguintes proteções:

- dados de uma conta não podem ser acessados por outra;
- um encontro encerrado não pode ser alterado;
- não pode existir mais de uma partida em andamento no mesmo encontro;
- dois lados da mesma partida não podem apontar para o mesmo time;
- times usados em uma partida devem pertencer ao encontro;
- placares nunca ficam negativos;
- um resultado inválido ou sem vencedor não pode ser confirmado;
- alterações compostas usam transações de banco de dados;
- encontros, partidas e participantes são bloqueados durante operações críticas para reduzir conflitos simultâneos;
- a sugestão de empréstimo é validada novamente no momento da confirmação.

## Fluxo recomendado durante um encontro

1. Entre no painel e crie o encontro.
2. Ajuste modo, pesos, meta, duração e critério do ranking.
3. Cadastre as pessoas rigorosamente na ordem em que chegaram.
4. Confira a formação automática e o resumo de equilíbrio.
5. Se necessário, troque jogadores antes do primeiro ponto ou gol.
6. Compartilhe o link do placar público.
7. Controle o placar e o cronômetro.
8. Corrija qualquer erro antes de confirmar o resultado.
9. Confirme o resultado no Dialog e confira a nova fila.
10. Registre imediatamente saídas e retornos.
11. Revise empréstimos e jogos incompletos antes de autorizá-los.
12. Use “Desfazer último” antes de alterar o placar seguinte, caso tenha confirmado algo errado.
13. Ao final, encerre o encontro para preservar o histórico e liberar a criação do próximo.

## Exemplo completo de rotação

Considere quatro times: A, B, C e D.

1. A enfrenta B; C e D aguardam.
2. A vence. B vai para o fim da fila e A permanece.
3. A enfrenta C e vence novamente.
4. A completou duas partidas consecutivas.
5. Como existem D e B esperando, dois times podem entrar.
6. D e B entram em quadra.
7. A vai para o fim da fila antes de C.
8. A fila passa a ser A, depois C.

Se apenas um time estivesse esperando no passo 5, A poderia permanecer além da segunda partida, porque não haveria dois times fora para substituir ambos os lados.

## Limites atuais

- O sistema trabalha com duas equipes por partida.
- Gênero está disponível nas opções mulher e homem.
- O nível de habilidade vai de 1 (iniciante) a 5 (muito avançado).
- O limite de permanência é fixo em duas partidas consecutivas.
- Não há empate definitivo no ranking: partidas empatadas de futebol ou futsal exigem a escolha de um vencedor.
- O cronômetro informa o fim do tempo previsto, mas não encerra a partida sozinho.
- O placar público atualiza por consulta periódica; Laravel Reverb não está instalado.
- Não há edição ou exclusão de partidas antigas confirmadas além da ação controlada de desfazer o último resultado.

Quero desenvolver um sistema web para organizar partidas recreativas de vôlei, controlar a fila de times, montar equipes equilibradas, selecionar substitutos e registrar histórico e ranking.

**1. Formatos de jogo**

- Duas equipes jogam por vez.
- Os formatos possíveis são 4×4, 5×5 e 6×6.
- O organizador escolhe o formato conforme a quantidade de pessoas presentes, buscando fazer a fila rodar mais rápido.
- O sistema deve mostrar quantos times completos podem ser formados e quantos jogadores ficam disponíveis para completar equipes.

**2. Placar**

- Cada partida é disputada até 15 pontos.
- Quando o placar chega a 14×14, começa um desempate: vence quem marcar primeiro três pontos adicionais.
- O desempate pode terminar em 17×14, 17×15 ou 17×16; não exige dois pontos de vantagem.
- O sistema deve permitir marcar e corrigir pontos e confirmar o resultado antes de atualizar a fila.

**3. Fila e permanência em quadra**

- Normalmente, o vencedor permanece em quadra e o perdedor vai para o fim da fila.
- Cada time pode disputar no máximo duas partidas consecutivas.
- Um time pode jogar mais de duas vezes consecutivas somente quando não houver dois times de fora disponíveis para entrar.
- O limite considera partidas consecutivas, não o total de partidas do encontro.
- Quando houver dois times de fora disponíveis e uma equipe atingir o limite, ela deve sair, mesmo que tenha vencido.
- Se os dois times que estavam em quadra saírem, o vencedor entra no fim da fila imediatamente antes do perdedor. Os times que já estavam esperando mantêm sua prioridade.

Exemplo:

1. A enfrenta B e vence.
2. B vai para o fim da fila; A permanece e enfrenta C.
3. A vence C, completando duas partidas consecutivas.
4. Havendo dois times de fora disponíveis, A e C saem.
5. Os dois primeiros times da fila entram.
6. A entra no fim da fila antes de C.

O sistema deve exibir a ordem da fila, os próximos times e o número de partidas consecutivas de cada equipe em quadra.

**4. Jogadores e presença**

Cada jogador deve ter:

- Nome.
- Gênero, para auxiliar na distribuição das equipes.
- Nível individual de habilidade.
- Situação no encontro: disponível, em quadra, aguardando ou foi embora.
- Time atual, quando houver.

Deve ser possível registrar chegadas e saídas durante o encontro. Quem foi embora não pode ser escolhido para partidas ou substituições.

**5. Formação e equilíbrio dos times**

Quero priorizar equipes com distribuição semelhante de homens e mulheres. No grupo atual, os homens costumam ter maior nível de jogo, mas o sistema deve registrar a habilidade individual para avaliar o equilíbrio com mais precisão.

Preferências:

- No 4×4, priorizar 2 homens e 2 mulheres quando houver participantes suficientes.
- Composições como 3 homens e 1 mulher são aceitáveis, desde que as equipes adversárias tenham equilíbrio semelhante.
- Evitar confrontos entre uma equipe com 3 mulheres e 1 homem e outra com 3 homens e 1 mulher.
- Aplicar o mesmo princípio aos formatos 5×5 e 6×6: aproximar a composição e o nível total das equipes.

Critério proposto para a formação automática:

1. Usar apenas jogadores presentes e disponíveis.
2. Formar equipes completas.
3. Aproximar a distribuição de homens e mulheres.
4. Minimizar a diferença de habilidade entre as equipes.
5. Considerar o tempo de espera e a quantidade de partidas disputadas para distribuir as oportunidades.

Quando não for possível alcançar o equilíbrio desejado, o sistema deve explicar a diferença e permitir ajustes pelo organizador.

**6. Times incompletos e substituições**

Nem sempre haverá times completos. O sistema deve escolher automaticamente quem pode completar uma equipe.

Exemplo: um jogador do time X vai embora. O sistema identifica a vaga e sugere o próximo jogador elegível.

Critério proposto para escolher substitutos:

- Estar presente e disponível.
- Não estar jogando outra partida.
- Priorizar jogadores sem time.
- Preservar o equilíbrio de gênero e habilidade.
- Entre candidatos equivalentes, priorizar quem espera há mais tempo e depois quem jogou menos partidas.

Se todos os jogadores disponíveis pertencerem a outros times, o sistema pode sugerir um empréstimo, mostrando o impacto na equipe de origem e exigindo confirmação do organizador. Não deve deslocar alguém silenciosamente nem deixar outro time incompleto sem avisar.

As substituições devem ficar registradas no histórico.

**7. Histórico e ranking**

Salvar permanentemente:

- Encontros de vôlei, com data e formato.
- Times e jogadores participantes.
- Composição de cada time em cada partida.
- Placar final e vencedor.
- Ordem das partidas.
- Substituições e saídas.

O ranking por time deve mostrar:

- Partidas disputadas.
- Vitórias e derrotas.
- Aproveitamento.
- Pontos marcados e sofridos.
- Saldo de pontos.

Critério inicial sugerido: ordenar por vitórias, depois aproveitamento e saldo de pontos. Esse critério deve ser configurável.

O histórico deve preservar os jogadores que participaram de cada partida, mesmo quando a composição do time mudar posteriormente.

**8. Interface e operação**

O sistema deve funcionar bem no celular, pois será usado durante os jogos.

A tela principal deve mostrar:

- Times em quadra e seus jogadores.
- Placar com botões grandes.
- Contagem do desempate quando houver 14×14.
- Partidas consecutivas de cada time.
- Fila de espera.
- Times incompletos e sugestões de substituição.
- Ação para confirmar o resultado e avançar para a próxima partida.

Também deve permitir cadastrar jogadores, marcar presença, formar ou ajustar times, consultar histórico e visualizar o ranking.

**9. Confiabilidade**

- Não permitir que um jogador esteja em dois times simultaneamente, exceto empréstimos explicitamente registrados.
- Não iniciar uma partida com equipes incompletas sem confirmação.
- Não registrar a mesma partida duas vezes.
- Permitir desfazer o último resultado, restaurando o placar, a fila e os contadores anteriores.
- Preservar histórico e ranking ao fechar ou atualizar a página.

As regras de placar e fila acima estão confirmadas. Os critérios de habilidade, substituição e desempate do ranking são propostas iniciais e devem ficar configuráveis.

**10. Lista de chegada e formação automática dos times**

A operação principal será preencher uma única lista de jogadores em ordem de chegada. Conforme o organizador adiciona os nomes, o sistema monta os times automaticamente, respeitando o formato escolhido: 4×4, 5×5 ou 6×6.

**A ordem de chegada tem prioridade sobre o equilíbrio dos times: quem chega primeiro deve jogar primeiro.**

Regras:

- Cada pessoa recebe uma posição na lista ao ser cadastrada no encontro.
- Para a primeira partida, o sistema seleciona as primeiras 8, 10 ou 12 pessoas presentes, conforme o formato.
- O equilíbrio é feito distribuindo essas pessoas entre as duas equipes. Não pode trazer alguém que chegou depois e deixar uma pessoa anterior esperando apenas para melhorar o equilíbrio.
- Os demais participantes formam os próximos times, seguindo a mesma ordem.
- Enquanto não houver pessoas suficientes para completar um time, elas ficam aguardando, mantendo sua prioridade.
- Novos participantes entram no fim da lista de chegada.
- Quem vai embora deixa de participar das próximas seleções.

Exemplo no 4×4: as pessoas nas posições 1 a 8 disputam a primeira partida. O sistema distribui essas oito pessoas entre os dois times buscando equilíbrio. As posições 9 a 12 formam o próximo time; as posições 13 a 16 formam o seguinte.

**Substituições**

Quando alguém sair de um time, a vaga deve ser preenchida pelo primeiro jogador elegível que estiver aguardando para completar uma equipe. O equilíbrio é considerado sem ultrapassar sua prioridade de chegada.

Se a substituição exigir retirar alguém de um time já completo, o sistema deve mostrar o impacto e pedir confirmação ao organizador.

**Relação com a fila de partidas**

A ordem de chegada determina a primeira oportunidade de jogar e a formação inicial dos times. Depois que um time entra em quadra, passam a valer as regras de vitória, permanência máxima e retorno à fila já definidas.

**Ajuste dos critérios anteriores**

O sistema deve primeiro respeitar a ordem de chegada e depois buscar o melhor equilíbrio possível entre os jogadores selecionados. Quando esses critérios entrarem em conflito, a ordem de chegada prevalece e a interface informa que não foi possível equilibrar totalmente as equipes.

**11. Stack e arquitetura**

O sistema será desenvolvido como uma única aplicação web utilizando:

- **PHP + Laravel:** regras de negócio, cadastro de jogadores, presença, fila, formação de times, partidas e ranking.
- **Livewire + Alpine.js:** interfaces interativas, incluindo atualização de placar, lista de chegada e organização dos times.
- **Tailwind CSS + TallStack UI:** interface responsiva, com prioridade para uso no celular.
- **PostgreSQL:** armazenamento permanente dos encontros, jogadores, times, partidas, substituições e histórico.
- **Laravel Reverb:** opcional, para sincronizar placar e fila em tempo real entre vários dispositivos.

As regras de negócio devem ficar separadas dos componentes Livewire, em serviços como:

| Serviço | Responsabilidade |
|---|---|
| `FormacaoTimesService` | Formar equipes respeitando a ordem de chegada e buscando equilíbrio entre os jogadores selecionados. |
| `FilaService` | Controlar a espera, a permanência em quadra e o retorno dos times à fila. |
| `SubstituicaoService` | Selecionar jogadores elegíveis para completar equipes e registrar substituições. |
| `PartidaService` | Controlar placar, desempate, confirmação de resultado e desfazer a última partida. |
| `RankingService` | Calcular estatísticas e classificação com base nos resultados confirmados. |

Os componentes Livewire devem cuidar da apresentação, validar as entradas e chamar esses serviços.

Operações que alteram várias informações ao mesmo tempo — como confirmar uma partida e reorganizar a fila — devem usar transações no banco de dados. O sistema também deve impedir confirmações duplicadas e alterações concorrentes que deixem a fila inconsistente.

Os testes devem priorizar as regras principais: ordem de chegada, limite de partidas consecutivas, exceção quando faltam dois times de fora, posição do vencedor antes do perdedor, desempate após 14×14, substituições e restauração da fila ao desfazer um resultado.

A primeira versão pode funcionar com um único dispositivo de controle. A sincronização com Reverb será adicionada caso seja necessário acompanhar ou operar o encontro em vários celulares.
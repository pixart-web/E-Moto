# Plano de redesign premium — E-MOTO

## Âmbito

Intervenção estritamente visual na branch `design/motogreen-premium-ui`. Não altera dados, base de dados, APIs, autenticação, contratos de persistência ou regras de negócio.

## Auditoria inicial

- A interface base está em `index.html`, `styles.css` e templates HTML gerados por `app.js`.
- O JavaScript depende extensivamente de IDs, classes, atributos `name` e `data-*`; estes seletores serão preservados.
- O calendário depende de `styles.css`, `calendar-notes-v37.css` no servidor e `calendar-notes-week-fix.css`.
- O stock usa imagens locais e `api/bike-images.php`; caminhos e fallbacks serão preservados.
- Existem múltiplas camadas históricas de CSS. Para minimizar regressões, o redesign utiliza `premium-ui.css`, carregado por último e sem alterar a lógica existente.
- Breakpoints existentes principais: 1200/1100/1050/900/760/680 px. O novo sistema mantém compatibilidade e acrescenta ajustes até 430 px.
- A aplicação Android é uma WebView da aplicação web; o redesign web é, por isso, partilhado sem alterar o projeto Android.

## Fases

1. Design system e estrutura global: tokens, sidebar clara, topbar, tipografia e foco.
2. Dashboard e componentes: métricas, cartões, listas, estados e tabelas.
3. Módulos operacionais: processos, clientes, stock, catálogo, oficina, agenda e restantes páginas.
4. Interação: formulários, modais, login, mensagens e estados.
5. Responsividade e acessibilidade: desktop, tablet, telemóvel, teclado e movimento reduzido.
6. Regressão: análise estática, testes existentes, validação visual e documentação de limitações.

## Riscos controlados

- Não renomear nem remover seletores usados por handlers.
- Não alterar cálculos ou templates funcionais para fins decorativos.
- Não remover CSS complementar do calendário.
- Não tocar em imagens reais nem na associação marca/modelo/cor.
- Não executar seeds, restauros, migrações ou testes de escrita contra produção.

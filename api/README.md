# API de Controle de Estoque e Vendas

API REST desenvolvida em **Laravel 11** para um módulo de **controle de estoque, vendas e relatórios** de um ERP, com foco em:

- Processamento de vendas assíncrono (fila)
- Controle de concorrência de estoque (locks e transações)
- Relatórios com agregações e filtros
- Tarefas agendadas para arquivar movimentações antigas

O código da aplicação Laravel está na pasta `api/` deste repositório.



## 1. Pré-requisitos

Antes de começar, você precisa ter instalado na máquina:

- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)
- [Git](https://git-scm.com/)
- [VsCode](https://code.visualstudio.com/download) ou alguma IDE de sua preferência.

Opcional (para desenvolvimento sem Docker):

- PHP 8.2 FPM +
- Composer 2
- PostgreSQL 14
- Laravel 10 +

> **Recomendado:** usar o fluxo via Docker descrito abaixo.

---

## 2. Clonar o repositório

Em um diretório de sua preferência:<br>

git clone git@github.com:SEU-USUARIO/lwsa-estoque-e-vendas.git<br>
cd lwsa-estoque-e-vendas<br>

---

## 3. Subindo os containers com Docker
No VSCode acesse via terminal na pasta raiz do projeto onde está localizado o arquivo docker-compose.yml.<br>
Rode o comando:<br>
`docker-compose up -d --build`

Esse comando irá iniciar os containers:<br>
`estoque_app` – container com PHP-FPM + Laravel
`estoque_nginx` – Nginx servindo a aplicação na porta 8000
`estoque_postgres` – PostgreSQL
`estoque_redis` – Redis (preparado para uso futuro)

Para conferir se todos os containers estão ativos rode o comando:<br>
`docker ps` - Ele vai exibir o status dos containers.

---

## 4. Configurando Laravel
No VSCode acesse via terminal na pasta raiz do projeto, rode o comando:<br>
`docker exec -it estoque_app bash` <br>
Obs: Dentro do container, o código Laravel (pasta api/) está montado em `/var/www`. Se em seu bash não estiver nesse caminho digite o comando: `cd /var/www` <br>

- 4.1 Criando arquivo `.env` e gerando a APP_KEY
Dentro de `/var/www` rode 3 comandos:<br>
`cp .env.example .env`<br>
`php artisan key:generate`<br>
`php artisan config:clear`<br><br>
**Importante!**<br>
Se ao enviar o comando `php artisan key:generate` você receber um retorno como esse:<br><br> <img width="1018" height="117" alt="image" src="https://github.com/user-attachments/assets/f5522607-41e0-42d8-b798-21f2c18529f7" /><br><br>
Siga esses passos:<br>
Ainda dentro de `/var/www` rode o comando:<br>
`composer install`
Isso cria a pasta vendor/ com todas as dependências do Laravel.<br>
Só será necessário na primeira vez ou quando composer.json for alterado.<br><br>

- 4.2 Configurar conexão com o banco (Postgres dentro do Docker)<br>
O arquivo `.env` já vem preparado. Os valores relevantes que devem ser inseridos ou modificados são:<br>

    ```
    APP_ENV=local
    APP_DEBUG=true
    APP_URL=http://localhost:8000

    DB_CONNECTION=pgsql
    DB_HOST=postgres
    DB_PORT=5432
    DB_DATABASE=estoque
    DB_USERNAME=estoque_user
    DB_PASSWORD=secret

    QUEUE_CONNECTION=database
    CACHE_DRIVER=file
    SESSION_DRIVER=file
    ```


    > **Observação:** As informações do arquivo `.env` estão expostas por se tratar de um projeto de teste. Em projetos reais, evite deixar qualquer informação do `.env` visível ou acessível publicamente.

    Se quiser mudar credenciais do banco, você também deve alterar o serviço `postgres`
no `docker-compose.yml`.

---

## 5. Rodando as migrations e seeders<br>
Nessa etapa vamos criar todas as tabelas necessárias para o projeto já com dados populados. Essa etapa pode demorar um pouco mais que o esperado e deixar o terminal em espera. <br><br>
Ainda dentro do container (`/var/www`):<br>
Rode o comando `php artisan migrate:fresh --seed`<br>

- Popula o banco com dados de teste:
    - 100 produtos (products)
    - Estoque inicial para todos os produtos (inventory_movements)
    - ~10.000 vendas (sales)
    - 20.000 a 50.000 itens de venda (sale_items)<br><br>

> **Observação:** Sempre que quiser “resetar” o ambiente, basta rodar `php artisan migrate:fresh --seed` de novo.<br>

---

## 6. Worker da fila (Processamento de vendas)<br>
O fluxo de vendas é assíncrono: o endpoint só cria a venda e coloca um job na fila.<br>
Quem processa de fato é o worker.<br>
Para ter uma experiência real na utilização, abra outro terminal e execute:<br>
`docker exec -it estoque_app bash`<br>
`cd /var/www`<br>
`php artisan queue:work`<br>
Nesse terminal, o work vai exibir todas as ações que a fila está solicitando. Caso você tenha criado alguma solicitação com o worker desligado, assim que ativado ele irá processar todos os pedidos. <br>
Você vai ver algo como:<br><br>
<img width="1053" height="146" alt="image" src="https://github.com/user-attachments/assets/47411022-c89d-4b41-b079-c855e246d3ba" /><br><br>

---

## 7. Acessando a aplicação
- Página padrão do Laravel:
    - [http://localhost:8000](http://localhost:8000)
- Endpoints da API (exemplos):
    - `GET http://localhost:8000/api/inventory`
    - `POST http://localhost:8000/api/inventory`
    - `POST http://localhost:8000/api/sales`
    - `GET http://localhost:8000/api/sales/{id}`
    - `GET http://localhost:8000/api/reports/sales`<br><br>
Recomendo o uso do Postman ou Insomnia para realizar os testes da API. Caso deseje utilizá-los, disponibilizo abaixo o arquivo de importação que cria automaticamente toda a estrutura de requisições, facilitando o processo de testes:<br><br>
[import para postman](https://github.com/MarceloGBbarros/lwsa-estoque-e-vendas/blob/feature/setup-laravel/api/lwsa-estoque-vendas.postman_collection.json)<br><br>
<img width="684" height="530" alt="image" src="https://github.com/user-attachments/assets/2adc600c-c762-47b2-b7e7-1e3a2457c6d5" /><br><br>

---
## 8. Usando a API
Nesta seção, apresentarei como utilizar os endpoints da API e explicarei a estrutura de cada um deles.<br>

- **Listar Estoque**<br>
  `curl "http://localhost:8000/api/inventory"` - Retornará todo o estoque e a informação de cada produto:<br>
```json
    {
      "items": [
        {
          "id": 1,
          "sku": "SKU-0001",
          "name": "Produto X",
          "cost_price": 30,
          "sale_price": 50,
          "current_stock": 118,
          "stock_value": 5900,
          "potential_profit": 2360
        }
      ],
      "total_stock_value": 5900,
      "total_potential_profit": 2360
    }
```

- **Listar Estoque com filtro por id do produto ou SKU**<br>
    **Por id:** <br>
  `curl "http://localhost:8000/api/inventory?product_id=1"` - Retornará as informações do produto de ID 1:<br>
      **Por SKU** <br>
  `curl "http://localhost:8000/api/inventory?sku=SKU-0001"` - Retornará as informações do produto de código SKU-0001:<br><br>
  **obs:** O SKU no exemplo acima é apenas uma demonstração para receber um retorno real utilize um SKU válido.

- **Registrar entrada de estoque (compra/reposição)** <br>
```curl
curl -X POST "http://localhost:8000/api/inventory" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "type": "in",
    "quantity": 10,
    "unit_cost": 50,
    "description": "Compra de reposição"
  }'
```

- **Exemplo de saída com estoque insuficiente (erro 422)** <br>
```curl
curl -X POST "http://localhost:8000/api/inventory" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "type": "out",
    "quantity": 9999,
    "unit_cost": 50,
    "description": "Tentativa de saída maior que o estoque"
  }'
```
<br>

**Irá retornar:** <br>
```json
{
  "message": "Estoque insuficiente",
  "details": "Estoque insuficiente para essa operação"
}
```
- **Criar uma venda (1 item)** <br>
```curl
curl -X POST "http://localhost:8000/api/sales" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      { "product_id": 1, "quantity": 2 }
    ]
  }'
```
- **Criar uma venda com múltiplos itens** <br>
```curl
curl -X POST "http://localhost:8000/api/sales" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      { "product_id": 1, "quantity": 3 },
      { "product_id": 2, "quantity": 5 }
    ]
  }'
```
**Em ambas as solicitações irá retornar 202:** <br>
```json
{
  "message": "Sale created and queued for processing",
  "data": {
    "id": 123,
    "status": "pending"
  }
}
```

Depois de o `queue:work` processar, consulte:
`curl "http://localhost:8000/api/sales/{id}"` <br>
Se o pedido estiver correto, seu status será alterado para **processed** e a quantidade disponível em estoque será atualizada no banco de dados. Caso algum item não possua estoque suficiente, o status do pedido será definido como **failed**.<br>

- **Ver relatório de vendas por período** <br>
`curl "http://localhost:8000/api/reports/sales?start_date=2025-01-01&end_date=2025-12-31"`

- **Ver relatório de vendas por período filtrado por sku** <br>
`curl "http://localhost:8000/api/reports/sales?start_date=2025-01-01&end_date=2025-12-31&sku=SKU-0001"`

## 9. Rodar tarefa agendada
A tarefa agendada roda diariamente às 02:00, mas você pode testar manualmente:

Dentro do container (`/var/www`) rode: <br>
`php artisan inventory:archive-old` <br>

A saída esperada é algo como:
Arquivando movimentações anteriores a YYYY-MM-DD HH:MM:SS...
Total de movimentações arquivadas: X <br>

Provavelmente o comando não irá encontrar nenhuma movimentação antiga. Para testar podemos fazer um update no banco através de algum gerenciador (eu indico o DBeaver). <br>
No DBeaver rode a sequinte query:<br>
```postgree
UPDATE inventory_movements
SET created_at = now() - INTERVAL '120 days',
    archived_at = NULL
WHERE id IN (1, 2, 3);
```
Essa query irá criar 3 registros com datas anteriores a 120 dias podendo ser atingidas pelo nosso arquivamento automatico.
<br>
Imagem antes e depois de rodar a query. <br><br>
<img width="579" height="126" alt="image" src="https://github.com/user-attachments/assets/b04b922e-72dd-4109-b249-3bd6cb165f3e" />
<br><br>


---

## 10. Testes automatizados
Nessa etapa vamos configurar o ambiente para teste.

- **Preparar ambiente de testes**
    - Dentro do container (`/var/www`), crie o `.env.testing`:<br>
    `cp .env .env.testing`<br>

    - No Postgres, crie o banco de teste (pode ser pela CLI ou por client, como DBeaver):
    `CREATE DATABASE estoque_test;`

    - O .env.testing deve estar assim:
    ```
    APP_ENV=testing 
    APP_DEBUG=true
    
    DB_CONNECTION=pgsql
    DB_HOST=postgres
    DB_PORT=5432
    DB_DATABASE=estoque_test
    DB_USERNAME=estoque_user
    DB_PASSWORD=secret
    
    QUEUE_CONNECTION=sync
    CACHE_DRIVER=array
    SESSION_DRIVER=array
    ```

    
- **Rodar os testes** <br>
  Dentro do container estoque_app rode (`docker exec -it estoque_app bash`): <br>
  `cd /var/www` <br>
  `php artisan test`

    Você verá o resumo das classes de teste (InventoryServiceTest, SalesServiceTest, SalesApiTest, etc.).<br><br>
    <img width="1063" height="423" alt="image" src="https://github.com/user-attachments/assets/381cc567-a135-4311-a0e9-7fee4bb527d9" />
    <br><br>

---

## 11. Melhorias futuras
 Algumas melhorias que eu faria em uma próxima iteração, e por quê:

 **Autenticação e autorização**
   - **O que fazer:**
     - Proteger os endpoints com autenticação.
     - Criar perfis de acesso (admin, operador, somente leitura).
     - Registrar auditoria de operações sensíveis (alterações de estoque e vendas).
   - **Por que isso é importante:**
     - Em um ERP real, exposição pública da API não deve ocorrer.
     - Perfis de acesso evitam que qualquer usuário consiga, por exemplo, lançar estoque ou cancelar vendas.
     - Auditoria é fundamental para rastrear **quem fez o quê** em caso de divergências de estoque ou suspeita de fraude.

2. **Paginação e filtros avançados**
   - **O que fazer:**
     - Adicionar paginação em `GET /api/inventory` e nos relatórios.
     - Incluir filtros por categoria, faixa de preço, status de produto (ativo/inativo).
     - Permitir ordenação por lucro, valor de estoque, quantidade vendida etc.
   - **Por que isso é importante:**
     - A lista de produtos e relatórios cresce com o tempo; sem paginação, as respostas podem ficar pesadas e lentas.
     - Filtros avançados dão poder para o usuário de negócio explorar o dado sem ter que montar consultas complexas.
     - Ordenação por métricas (lucro, valor de estoque, etc.) ajuda a priorizar ações, por exemplo, focar nos produtos que mais geram receita ou carregam mais capital parado.

3. **Uso de Redis para cache e filas**
   - **O que fazer:**
     - Migrar `CACHE_DRIVER` e `QUEUE_CONNECTION` para `redis` em ambientes de produção.
     - Melhorar throughput de jobs e reduzir leitura de disco.
     - Adicionar monitoramento básico de filas (tamanho, tempo médio de processamento).
   - **Por que isso é importante:**
     - Redis é muito mais performático do que arquivo/banco para cache e filas, especialmente com alto volume de requisições.
     - Usar Redis reduz latência tanto nas consultas cacheadas quanto no consumo de jobs.
     - Monitorar filas permite detectar gargalos (ex.: jobs acumulando) antes de impactar o usuário final.

4. **Observabilidade e monitoramento**
   - **O que fazer:**
     - Expor métricas (Prometheus/Grafana, por exemplo) sobre:
       - tempo de resposta dos endpoints
       - jobs processados, falhos e em fila
       - volume diário de vendas e movimentações
     - Centralizar logs em um stack 
   - **Por que isso é importante:**
     - Sem métricas, é difícil saber se a API realmente está atendendo os SLAs (ex.: relatórios em < 2s).
     - Acompanhando jobs e filas, fica mais fácil identificar falhas recorrentes ou picos anormais de carga.
     - Logs centralizados ajudam no diagnóstico de problemas em produção (ex.: falha ao processar vendas específicas) sem depender do servidor individual.

5. **Escalabilidade de dados**
   - **O que fazer:**
     - Definir estratégia de particionamento ou arquivamento de `sales` e `sale_items` para 100k+ / 1M+ registros.
     - Criar tabelas de histórico separadas para relatórios de longo prazo.
     - Revisar e ajustar índices com base em uso real (queries mais frequentes).
   - **Por que isso é importante:**
     - À medida que o volume de vendas cresce, algumas consultas começam a degradar mesmo com índices – principalmente relatórios de longos períodos.
     - Separar dados “quentes” (recente) e “frios” (histórico) mantém a parte operacional leve, sem perder a rastreabilidade histórica.
     - Manter índices alinhados com o padrão de uso evita desperdício de recursos (índices inúteis) e melhora performance em consultas críticas.

6. **Camada de apresentação / dashboard**
   - **O que fazer:**
     - Criar um pequeno frontend em Vue.js (ou similar) para:
       - visualizar estoque em tempo real
       - acompanhar vendas em lista
       - ver gráficos de faturamento, lucro e produtos mais vendidos
   - **Por que isso é importante:**
     - Para o usuário de negócio, uma interface visual traz muito mais valor do que apenas endpoints.
     - Dashboards ajudam na tomada de decisão rápida (ex.: identificar produtos com estoque baixo ou de alta rotatividade).
     - Como a vaga cita Vue.js, essa camada mostraria também seu domínio no frontend da stack proposta, além da parte de backend.

7. **Mais testes automatizados**
   - **O que fazer:**
     - Ampliar os testes para cobrir relatórios (`ReportsService`).
     - Adicionar testes de API mais completos para os filtros (SKU, período, product_id).
     - Criar cenários específicos de concorrência (ex.: múltiplas vendas simultâneas no mesmo produto).
   - **Por que isso é importante:**
     - Os testes atuais cobrem o “feliz” e algumas regras críticas, mas a superfície do sistema é maior.
     - Relatórios e filtros são áreas onde bugs de lógica são comuns, e testes protegem contra regressões.
     - Concorrência é difícil de validar manualmente; ter testes focados nisso aumenta a confiança de que os locks e transações estão se comportando como esperado em cenários extremos.


    # Decisões Arquiteturais
## Banco de dados
- **Qual banco escolheu e por quê?**
    - Escolhi PostgreSQL 14, pelos seguintes motivos:
        - Suporte robusto a transações e locks pessimistas (FOR UPDATE), fundamentais para garantir consistência de estoque em cenários com concorrência evitando que duas transações modifiquem o mesmo dado ao mesmo tempo.
        - Desempenho consistente para consultas agregadas e filtros por período, importantes para os endpoints de relatórios de vendas.
        - Integração simples com Docker e com o ecossistema Laravel.
        - Forte aderência a cenários típicos de ERP/SaaS que precisam de confiabilidade em operações financeiras e de estoque.<br><br>
        
- **Como modelou as tabelas?**
    - **products**
      - Representa o catálogo de produtos.
      - Principais campos:
          - id
          - sku (código único)
          - name
          - cost_price
          - sale_price
          - current_stock (estoque atual)
          - timestamps

     - **inventory_movements**    
        - Histórico de movimentações de estoque (entrada e saída).
        - Principais campos:
            - id
            - product_id (FK → products)
            - type (in / out)
            - quantity
            - unit_cost
            - description
            - archived_at (nullable – usado para arquivamento de dados antigos)
            - timestamps

    - **sales** 
        - Registra as vendas efetuadas
        - Principais campos:
            - id
            - total_value
            - total_cost
            - profit
            - status (pending, processed, failed)
            - timestamps

    - **sale_items** 
        - Itens pertencentes a uma venda.
        - Principais campos:
            - id
            - sale_id (FK → sales)
            - product_id (FK → products)
            - quantity
            - unit_price
            - unit_cost
            - total_line
            - profit_line
            - timestamps
  
- **Quais índices criou e por quê?**
    - `products.sku` (unique) 
        - Por ser uma busca frequente em relatórios e filtros garante unicidade de código de produto.
    -  `sales.status`, `sales.created_at`
        - Por ser usadas como filtros para relatórios por período e/ou status esses índices aceleram consultas agregadas.

    -  `sale_items.sale_id`, `sale_items.product_id`,`inventory_movements.product_id`
        - Uso joins frequentes entre vendas/itens e produtos, com isso os índices nas FKs evitam full scans desnecessários.

    - `inventory_movements.archived_at`
        - Usado como filtro frequente na tarefa de arquivamento (seleciona movimentações sem `archived_at` e com `created_at` antigo)

- **Quais constraints aplicou?**
    - **Chaves estrangeiras:**
        - `inventory_movements.product_id` → `products.id`
        - `sale_items.product_id` → `products.id`
        - `sale_items.sale_id` → `sales.id`
    - **Unique:**
        - `products.sku` para garantir que não existam dois produtos com o mesmo código.

    - **Integridade de domínio:**
        - Validação de tipos (`type` em `inventory_movements`, `status` em `sales`) feita na aplicação.

- Diagrama ER (pode usar Mermaid ou imagem)
  <img width="1277" height="647" alt="image" src="https://github.com/user-attachments/assets/72cda2ab-503c-4ab1-8bfc-b49796c3a43f" />

## Arquitetura:
  - **Como organizou a estrutura de pastas?**
  Dentro da pasta api/
```
app/
  Http/
    Controllers/
      Api/
        InventoryController.php
        SalesController.php
        ReportsController.php
    Requests/
      StoreInventoryMovementRequest.php
      StoreSaleRequest.php
      SalesReportRequest.php
  Models/
    Product.php
    InventoryMovement.php
    Sale.php
    SaleItem.php
  Services/
    InventoryService.php
    SalesService.php
    ReportsService.php
  Jobs/
    ProcessSaleJob.php
  Exceptions/
    InsufficientStockException.php
  Console/
    Commands/
      ArchiveOldInventoryMovements.php
    Kernel.php
database/
  migrations/
  factories/
  seeders/
```

 - **Quais camadas criou (Controller, Service, Repository, etc.)?**
  - **Controllers**
    - `InventoryController`, `SalesController`, `ReportsController` Responsáveis por:
       - receber a requisição HTTP
       - acionar o Service correspondente
       - retornar respostas JSON.
    - Validação feita com Form Requests (`StoreInventoryMovementRequest`, `StoreSaleRequest`, `SalesReportRequest`).
  
  - **Services**
    - `InventoryService`
      - Registra movimentações de entrada/saída
      - Usa transações e lockForUpdate em Product
      - Atualiza current_stock
      - Invalida/cacheia informações de estoque.

  
    - `SalesService`
      - Cria Sale com status pending
      - Cria SaleItem
      - Dispara ProcessSaleJob (fila)

    - `ReportsService`
      - Implementa queries agregadas de vendas com filtros por período e SKU
      - Aplica cache para relatórios frequentes.

    - **Jobs**
      - `ProcessSaleJob`
        - Responsável por processar a venda em background
        - Usa `DB::transaction` e `lockForUpdate` em `Sale` e `Product` para garantir consistência
        - Valida estoque, debita `current_stock`, registra `inventory_movements` de saída, calcula totais e marca a venda como `processed` ou `failed`.

    - **Exceptions**
      - `InsufficientStockException`
        - Lançada quando não há estoque suficiente em uma operação síncrona (ex.: `POST` `/api/inventory`)
        - Tratada em `bootstrap/app.php` para retornar 422 com JSON padronizado.

    - **Console Commands + Scheduler**
      - `ArchiveOldInventoryMovements`
        - Comando que arquiva movimentações de estoque mais antigas que 90 dias.
      - Agendado em `app/Console/Kernel.php` para rodar diariamente.
        

- **Por que escolheu essa arquitetura?**
  - Controllers ficam focados em HTTP e DTOs de entrada/saída.
  - Services encapsulam a regra de negócio (estoque, vendas, relatórios).
  - Jobs separam aquilo que é pesado/assíncrono, como processamento de venda e atualização de estoque, para não bloquear a resposta da API.
  - Commands + Scheduler tratam de manutenção periódica (arquivamento de dados antigos).
  - Exceptions customizadas permitem tratamento consistente de erros e respostas HTTP adequadas.
  Essa organização facilita a evolução do sistema (ex.: adicionar novos canais de entrada além da API HTTP) e melhora a legibilidade/testabilidade do código.

- **Quais padrões de design aplicou e onde?**
  - Service Layer: centraliza regras de negócio em classes de serviço.
  - Command Pattern: usado no comando `ArchiveOldInventoryMovements`.
  - Job/Queue: encapsula a unidade de trabalho assíncrono (`ProcessSaleJob`).
  - Exception Personalizada: `InsufficientStockException` para representar um erro de domínio com um contrato de resposta HTTP específico.
  - Active Record (Eloquent): para mapeamento objeto-relacional com os modelos.

---



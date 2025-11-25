# API de Controle de Estoque e Vendas

API REST desenvolvida em **Laravel 11** para um módulo de **controle de estoque, vendas e relatórios** de um ERP, com foco em:

- Performance (consultas otimizadas, índices, cache, filas)
- Concorrência (transações + locks pessimistas)
- Escalabilidade (processamento assíncrono)
- Base de dados realista (10k+ vendas)

O código da aplicação Laravel está na pasta `api/` deste repositório.

---

## 1. Stack e Tecnologias

- **Linguagem:** PHP 8.2 (PHP-FPM)
- **Framework:** Laravel 11
- **Banco de Dados:** PostgreSQL
- **Cache / Sessão / Fila:**  
  - Ambiente atual: cache/sessão em `file`, fila em `database`
  - Preparado para uso com Redis se desejado
- **Containers:** Docker + Docker Compose
  - `estoque_app` – PHP-FPM + Laravel
  - `estoque_nginx` – Nginx
  - `estoque_postgres` – PostgreSQL 14
  - `estoque_redis` – Redis (opcional; pode ser ligado depois)
- **Outros:**
  - Migrations + Seeders + Factories
  - Fila de jobs (`queue:work`)

---

## 2. Como rodar o projeto

### 2.1. Pré-requisitos

- Docker
- Docker Compose
- Git

### 2.2. Clonar o repositório

```bash
git clone git@github.com:SEU-USUARIO/lwsa-estoque-e-vendas.git
cd lwsa-estoque-e-vendas

### 2.3. Clonar o repositório
docker-compose up -d --build

2.4 Configurar o Laravel
docker exec -it estoque_app bash
cd /var/www
cp .env.example .env  # se ainda não existir
php artisan key:generate

2.4.1 Configurar o .env
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

2.5 Migrations e seeders simulando 10k em vendas
php artisan migrate:fresh --seed

2.6 Rodar worker, isso libera os testes de fila para processamento assíncrono
docker exec -it estoque_app bash
cd /var/www #normalmente já vem no caminho certo, mas vale validar se está nesse diretório
php artisan queue:work

2.7 Acessos da aplicação

Página padrão do Laravel:
http://localhost:8000

Endpoints de API (exemplos):
http://localhost:8000/api/inventory
http://localhost:8000/api/sales
http://localhost:8000/api/reports/sales

3. Modelagem e tabelas do banco de dados

3.1. Tabelas principais

products

id

sku (único)

name

cost_price

sale_price

current_stock

timestamps

Índices: sku (unique), name

inventory_movements

id

product_id (FK → products)

type (in / out)

quantity

unit_cost

description

timestamps

Índices: product_id, created_at

sales

id

total_value

total_cost

profit

status (pending, processed, failed)

timestamps

Índices: status, created_at

sale_items

id

sale_id (FK → sales)

product_id (FK → products)

quantity

unit_price

unit_cost

total_line

profit_line

timestamps

Índices: sale_id, product_id

4. Arquitetura de aplicação

4.1 Estrutura de pastas
api/
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
  database/
    migrations/
    factories/
    seeders/

4.2 Camadas de negócio
4.2.1 Controllers (API)

Recebem e validam input (via Form Requests)

Chamam Services

Retornam JSON normalizado

4.2.2 Services

    4.2.2.1 InventoryService:

        Registro de movimentos de estoque (entrada/saída)

        Concorrência (transações + lockForUpdate)

        Invalidação de cache de estoque

    4.2.2.2 SalesService:

        Criação de vendas (pending)

        Criação de itens de venda

        Disparo de job assíncrono (ProcessSaleJob)

    4.2.2.3 ReportsService:

        Consultas agregadas de vendas (SUM, GROUP BY)

        Filtros por período e SKU

        Cache de relatórios (Cache::remember)

4.2.3 Jobs

    4.2.3.1 ProcessSaleJob:

        Executado em fila (ShouldQueue)

        Abre transação

        Usa lockForUpdate() em sales e products

        Verifica estoque, atualiza current_stock

        Cria inventory_movements de saída

        Calcula totais da venda e lucro

4.3 Padrões DDD
    Service Layer – encapsula regras de negócio

    DTO implícito via arrays validados (payload validado por Request, repassado ao Service)

    CQRS “leve” – comandos (POST /sales) separados das consultas (GET /reports/sales)

    Event-driven via Job/Fila – criação de venda síncrona, processamento assíncrono

5. Performance e segurança

5.1 Performance
    5.1.1 Índices sob medida:

        sales.created_at, sales.status → relatórios por período + status

        products.sku → filtro por SKU

        sale_items.sale_id, sale_items.product_id → joins e agregações

    5.1.2 Consultas agregadas:

        Relatórios usam DB::table com SUM/GROUP BY, sem carregar models inteiros.

    5.1.3 Cache:

        GET /api/inventory → Cache::remember('inventory:summary', 60s, ...)

        GET /api/reports/sales → chave de cache por período + SKU, TTL 120s

    5.1.4 Filas:

        POST /api/sales - é rápido:

        grava venda como pending

        enfileira ProcessSaleJob

    5.1.5 Heavy lifting (estoque, cálculos) é feito em background (queue:work)

5.2 Concorrência e integridade
    5.2.1 Transações (DB::transaction)

    5.2.2 Locks pessimistas (lockForUpdate())

        5.2.2.1 Atualizações de estoque usam:

                    Product::whereKey($id)->lockForUpdate()

        5.2.2.2 Processamento de venda:

                    Sale::lockForUpdate()->with('items')->findOrFail($saleId)

                    Lock também nos products envolvidos

    5.2.3 Validação de regras de negócio:

        Não permite saída de estoque com quantidade maior que o estoque disponível

        Marca venda como failed se qualquer item não tiver estoque suficiente

5.3 Segurança
    5.3.1 Validação de entrada com FormRequest:

       5.3.1.1 Tipos, ranges, relacionamento com banco (exists:products,id)

    5.3.2 Separação de responsabilidades (Controllers finos, Services tratam regras)

    5.3.3 Ambiente de desenvolvimento explícito (APP_DEBUG=true apenas em local)


6. Endpoints e exemplos de requisições

6.1 Estoque
    POST /api/inventory – registrar movimento de estoque

    Sugestão de json
    {
    "product_id": 1,
    "type": "in",
    "quantity": 10,
    "unit_cost": 50.0,
    "description": "Compra de reposição"
    }

    exemplo com curl:
        curl -X POST http://localhost:8000/api/inventory \
        -H "Content-Type: application/json" \
        -d '{
            "product_id": 1,
            "type": "in",
            "quantity": 10,
            "unit_cost": 50.0,
            "description": "Compra de reposição"
        }'


        GET /api/inventory – situação atual do estoque

        curl http://localhost:8000/api/inventory

        Exemplo de resposta esperada:
        {
            "items": [
                {
                "id": 1,
                "sku": "SKU-ABC123",
                "name": "Produto X",
                "cost_price": 30,
                "sale_price": 50,
                "current_stock": 120,
                "stock_value": 6000,
                "potential_profit": 2400
                }
            ],
            "total_stock_value": 6000,
            "total_potential_profit": 2400
        }

    6.2 Vendas
        POST /api/sales – registrar uma venda (assíncrona)

        Exemplo usando json:
        {
        "items": [
            { "product_id": 1, "quantity": 2 },
            { "product_id": 3, "quantity": 5 }
        ]
        }

        Exemplo curl
        curl -X POST http://localhost:8000/api/sales \
        -H "Content-Type: application/json" \
        -d '{
            "items": [
            { "product_id": 1, "quantity": 2 },
            { "product_id": 3, "quantity": 5 }
            ]
        }'

        Retorno esperado:

        {
         "message": "Sale created and queued for processing",
            "data": {
                "id": 123,
                "status": "pending"
            }
        }

        GET /api/sales/{id} – detalhes da venda
        
        curl http://localhost:8000/api/sales/123

        Resposta esperada:
        {
            "id": 123,
            "total_value": 350.0,
            "total_cost": 210.0,
            "profit": 140.0,
            "status": "processed",
            "items": [
                {
                "id": 1,
                "product_id": 1,
                "quantity": 2,
                "unit_price": 50.0,
                "unit_cost": 30.0,
                "total_line": 100.0,
                "profit_line": 40.0
                }
            ]
        }

6.3 Relatórios 
    GET /api/reports/sales – relatório por período e SKU

    Parâmetros (query params):

    start_date (obrigatório)

    end_date (obrigatório)

    sku (opcional)

    exemplos:
    sem sku - curl "http://localhost:8000/api/reports/sales?start_date=2025-01-01&end_date=2025-12-31"

    com sku - curl "http://localhost:8000/api/reports/sales?start_date=2025-01-01&end_date=2025-12-31&sku=SKU-ABC123"

    retorno esperado:
    {
        "filters": {
            "start_date": "2025-01-01",
            "end_date": "2025-12-31",
            "sku": null
        },
        "totals": {
            "total_sales": 123456.78,
            "total_profit": 34567.89,
            "total_quantity": 9876
        },
        "by_product": [
            {
            "id": 1,
            "sku": "SKU-ABC123",
            "name": "Produto X",
            "quantity_sold": 120,
            "sales_value": 5600,
            "profit_value": 2300
            }
        ]
    }


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

git clone git@github.com:SEU-USUARIO/lwsa-estoque-e-vendas.git
cd lwsa-estoque-e-vendas

### 2.3. Subir os containers
Na rais do projeto:
docker-compose up -d --build

### 2.4 Configurar o Laravel
docker exec -it estoque_app bash
cd /var/www
cp .env.example .env
php artisan key:generate
php artisan config:clear

### 2.4.1 Configurar o .env
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

### 2.5 Migrations e seeders simulando 10k em vendas

php artisan migrate:fresh --seed

Isso irá criar e popular:
100 produtos (products)
Estoque inicial (inventory_movements)
~10.000 vendas (sales)
20k–50k itens de venda (sale_items)

### 2.6 Rodar worker, esse comando processa os pedidos de forma assíncronas
docker exec -it estoque_app bash
cd /var/www #normalmente já vem no caminho certo, mas vale validar se está nesse diretório
php artisan queue:work

### 2.7 Acessos da aplicação

Página padrão do Laravel:
http://localhost:8000

Endpoints de API (exemplos):
http://localhost:8000/api/...
Use o arquivo [https://github.com/MarceloGBbarros/lwsa-estoque-e-vendas/blob/feature/setup-laravel/api/lwsa-estoque-vendas.postman_collection.json] para importar no postman ou insonia para facilitar a utilização dos endpoints

3. Modelagem e tabelas do banco de dados

3.1. Tabelas principais

**products**
id
sku (único)
name
cost_price
sale_price
current_stock
timestamps
Índices: sku (unique), name

**inventory_movements**
id
product_id (FK → products)
type (in / out)
quantity
unit_cost
description
timestamps
Índices: product_id, created_at

**sales**
id
total_value
total_cost
profit
status (pending, processed, failed)
timestamps
Índices: status, created_at

**sale_items**
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

### 4. Arquitetura de aplicação

- 4.1 Estrutura de pastas<br>
api/<br>
  app/<br>
    Http/<br>
      Controllers/<br>
        Api/<br>
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
  database/
    migrations/
    factories/
    seeders/


**4.2 Camadas de negócio**

**4.2.1 Controllers (API)**
Recebem requisições HTTP
Chamam Services
Retornam JSON normalizado

**4.2.2 Services**

    **4.2.2.1 InventoryService:**
        Registro de movimentos de estoque (in/out)
        Concorrência (transações + lockForUpdate) em produtos
        Atualiza current_stock
        Invalidação de cache de estoque

    **4.2.2.2 SalesService:**
       Cria Sale com status pending
       Cria SaleItems
       Despacha ProcessSaleJob

    **4.2.2.3 ReportsService:**
        Monta relatórios agregados de vendas por período e SKU
        Usa DB::table com SUM/GROUP BY
        Aplica cache por combinação de filtros

**4.2.3 Jobs**

    **4.2.3.1 ProcessSaleJob:**

        Executa em fila (ShouldQueue)
        Transação + lockForUpdate em Sale e Product
        Valida estoque para todos os itens:
            Se faltar estoque → marca Sale como failed e não altera estoque
            Se houver estoque → debita current_stock, cria inventory_movements de saída, calcula total_value, total_cost, profit e marca processed
       

**4.3 Exceptions**
    ** 4.3.1 InsufficientStockException **
        Lançada quando não há estoque suficiente em operações síncronas
        Mapeada em bootstrap/app.php para retornar HTTP 422 com JSON amigável
    
### 5. Decissões Arquiteturais (resumo)
    **Service Layer para desacoplar regras de negócio de controllers.**
    **Concorrência e integridade:**
        **Uso de DB::transaction e lockForUpdate() em:**
            Atualizações de estoque
            Processamento de vendas
        **Garante que o current_stock não fique negativo mesmo com múltiplas requisições simultâneas.**
        
    **Processamento assíncrono de vendas**
        POST /api/sales é rápido (retorna 202 imediatamente).
        Lógica pesada (estoque, movimentos, cálculos) roda no ProcessSaleJob.
        Venda é marcada como processed ou failed.
        
    **Performance e relatórios**
        Consultas agregadas via DB::table com SUM/GROUP BY.
        Índices nos campos usados em filtros/joins (created_at, status, sku, FKs).
        
    **Cache aplicado em:**
        GET /api/inventory
        GET /api/reports/sales por combinação de período + SKU.
        
    **Manutenção de dados**
        Campo archived_at em inventory_movements.
        Comando agendado para arquivar movimentações com +90 dias.

### 6. Endpoints e exemplos
    **6.1 Estoque**
        **6.1.1 GET /api/inventory**
        Lista o estoque atual com valores totais e lucro projetado.
            Parâmetros de query:
            product_id (opcional)
            sku (opcional)

            Exemplos:
            # Todos os produtos
            curl "http://localhost:8000/api/inventory"
            
            # Filtrar por id
            curl "http://localhost:8000/api/inventory?product_id=1"
            
            # Filtrar por SKU
            curl "http://localhost:8000/api/inventory?sku=SKU-0001"

    ** 6.1.2 POST /api/inventory **
    Registra um movimento de estoque.
    Exemplo de body json
    {
      "product_id": 1,
      "type": "in",
      "quantity": 10,
      "unit_cost": 50,
      "description": "Compra de reposição"
    }

    **6.2 Vendas**
        **6.2.1 POST /api/sales **
        Cria uma venda com múltiplos itens e dispara o processamento assíncrono.
        Exemplo de body:
        {
          "items": [
            { "product_id": 1, "quantity": 2 },
            { "product_id": 2, "quantity": 5 }
          ]
        }
        
    Funcionamento:
    ProcessSaleJob é enfileirado.
    Worker (queue:work) processa:
    Se houver estoque suficiente para todos os itens → status = "processed", estoque reduzido.
    Se faltar estoque em qualquer item → status = "failed", estoque não é alterado.

    **6.2.2 GET /api/sales/{id}**
    Retorna detalhes da venda + itens.
    curl "http://localhost:8000/api/sales/{id}"

    **6.3 Relatórios **
    GET /api/reports/sales
    Relatório de vendas por período, com filtro opcional por SKU.
    Query params:
    start_date (obrigatório) – YYYY-MM-DD
    end_date (obrigatório)
    sku (opcional)

    Exemplos:
    # Período completo
    curl "http://localhost:8000/api/reports/sales?start_date=2025-01-01&end_date=2025-12-31"

    # Filtrado por SKU
    curl "http://localhost:8000/api/reports/sales?start_date=2025-01-01&end_date=2025-12-31&sku=SKU-0001"

    
## 7. Tarefas Agendadas (scheduler)
    Foi implementada uma tarefa para arquivar movimentações de estoque antigas:
    Comando: php artisan inventory:archive-old
    Lógica:
    Busca inventory_movements com:
    created_at < now() - 90 dias
    archived_at IS NULL
    Processa em chunks (chunkById(1000))
    Marca archived_at = now()
    
Agendamento em app/Console/Kernel.php:
$schedule->command('inventory:archive-old')->dailyAt('02:00');
Em produção, a recomendação é configurar um cron chamando o scheduler:
php /var/www/artisan schedule:run

## 8. Testes Automatizados
    8.1 Ambiente de teste
        Banco de testes: estoque_test
        Configurado em .env.testing (cópia de .env, ajustando DB_DATABASE).
        Fila em modo sync, cache/sessão em array.

    8.2 Testes imlementados
    tests/Feature/InventoryServiceTest.php
        Registra entrada de estoque
        Registra saída com estoque suficiente
        Garante que saída com estoque insuficiente lança exceção e não altera current_stock
    
    tests/Feature/SalesServiceTest.php
        Cria venda com status pending + itens
        Usa Queue::fake() para garantir que ProcessSaleJob é enfileirado

    tests/Feature/SalesApiTest.php
        POST /api/sales retorna 202 Accepted
        Garante que o job é enfileirado para processar a venda

    8.3 Como rodar os testes
        Dentro do container:
        docker exec -it estoque_app bash
        cd /var/www
        cp .env .env.testing   # se ainda não existir
        php artisan test

## 9. Melhorias
9. Melhorias Futuras
    Escalabilidade:
        Mudar fila para Redis/Kafka em ambientes de alto volume.
        Sharding/particionamento de tabelas de sales/sale_items por período.
        Arquivamento de sales históricos em tabelas separadas.

    Segurança:
        Autenticação (ex.: Laravel Sanctum) e autorização por perfil (admin / operador).
        Rate limiting em endpoints críticos.
        Logs auditáveis de alterações de estoque.

    API/UX:
        Paginação e filtros avançados em /api/inventory e /api/reports/sales.
        DTOs/Resources específicos para padronizar ainda mais as respostas.

    Observabilidade:
        Métricas de fila, tempo médio de processamento de venda, número de falhas.
        Dashboard básico de acompanhamento (jobs pendentes, falhas, etc.).

    


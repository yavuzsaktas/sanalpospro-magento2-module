# SanalPosPro - Development Phases

This document summarizes the three-phase development process of the `Paythor_SanalPosPro` and `Eticsoft_PaythorClient` modules with technical details.

---

## Module Architecture

```
Eticsoft_PaythorClient          Paythor_SanalPosPro
  (API Client)                    (Payment Integration)
  ├─ SDK logic                    ├─ Gateway API (Phase 2)
  ├─ HTTP requests                ├─ Admin Config (Phase 1)
  └─ SanalPosPro API communication├─ Checkout UI (Phase 3)
                                  └─ Observer / Handler
```

The `Paythor_SanalPosPro` module depends on `Eticsoft_PaythorClient` and uses its SDK/API layer.

---

## Phase 1 - Skeleton, Dependencies, and Admin Configuration

### Goal
Set up the module skeleton, register it with Magento, and create the admin panel configuration and database schema.

### Created / Updated Files

| File | Description |
|---|---|
| `etc/module.xml` | Module declaration. `setup_version` is not used (Declarative Schema). Dependencies on `Eticsoft_PaythorClient`, `Magento_Payment`, `Magento_Sales`, `Magento_Checkout`, and `Magento_Store` are defined with `<sequence>`. |
| `etc/adminhtml/system.xml` | Admin panel settings: active state, title, payment action (authorize/capture), test mode, API Key, API Secret (obscure), Webhook Secret (obscure), currency, and sort order. |
| `etc/config.xml` | Default values: `model` -> `PaythorSanalPosProFacade`, `can_authorize`, `can_capture`, `can_refund`, `can_void`, `active`, `title`, `sort_order`, and defaults for API fields. |
| `etc/acl.xml` | Access Control List: resource definitions for `Paythor_SanalPosPro::config`, `::paythor`, `::connect`, and `::transactions`. |
| `etc/db_schema.xml` | `paythor_transaction_log` table: `entity_id`, `order_id`, `quote_id`, `paythor_transaction_id`, `paythor_process_id`, `status`, `amount`, `currency`, `request_payload`, `response_payload`, `created_at`, `updated_at`. Foreign key: `sales_order.entity_id`. |
| `etc/db_schema_whitelist.json` | Declarative Schema whitelist file. |

### Technical Decisions
- The `setup_version` attribute was not used in line with `.cursorrules`; all DB changes are managed through `db_schema.xml`.
- The `api_secret` and `webhook_secret` fields were defined as `obscure` types (encrypted storage).
- Sensitive data (`api_key`, `api_secret`, `webhook_secret`, `sandbox_mode`) was marked as `environment` via `TypePool` in `di.xml` -> written to `env.php`, not stored in the DB.

### Verification
```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
```

---

## Phase 2 - Gateway Backend (Payment Provider Gateway API)

### Goal
Set up the `authorize`, `capture`, `refund`, and `void` transaction flows according to the Magento 2 Commerce Payment Provider Gateway architecture. Wrap the existing `PaythorAdapter` SDK with the Gateway API layer.

### Architecture Diagram

```
placeOrder()
  │
  ▼
CommandPool (di.xml)
  ├─ authorize -> GatewayCommand
  │    ├─ RequestBuilder (AuthorizationDataBuilder + PaymentDataBuilder)
  │    ├─ TransferFactory
  │    ├─ PaythorGatewayClient -> PaythorAdapter.createPaymentFromGateway()
  │    ├─ GeneralResponseValidator
  │    └─ HandlerChain (TransactionIdHandler + PaymentDetailsHandler)
  │
  ├─ capture -> GatewayCommand
  │    ├─ CaptureDataBuilder + PaymentDataBuilder
  │    ├─ PaythorGatewayClient -> PaythorAdapter.captureFromGateway()
  │    └─ ...
  │
  ├─ refund -> GatewayCommand
  │    ├─ RefundDataBuilder + PaymentDataBuilder
  │    ├─ PaythorGatewayClient -> PaythorAdapter.refundFromGateway()
  │    └─ ...
  │
  └─ void -> GatewayCommand
       ├─ VoidDataBuilder + PaymentDataBuilder
       ├─ PaythorGatewayClient -> PaythorAdapter.voidFromGateway()
       └─ ...
```

### Created / Updated Files

| File | Description |
|---|---|
| **Gateway/Http/TransferFactory.php** | `TransferFactoryInterface` implementation. Converts request data into a `TransferInterface` object. |
| **Gateway/Http/Client/PaythorGatewayClient.php** | `ClientInterface` implementation. Calls the relevant bridge method on `PaythorAdapter` based on the transaction type (`authorize`, `capture`, `refund`, `void`). |
| **Gateway/Request/PaymentDataBuilder.php** | Shared payment data: `order_id`, `amount`, `currency`, `customer_email`. `BuilderInterface`. |
| **Gateway/Request/AuthorizationDataBuilder.php** | `action: authorize`, `paythor_token`, and card details. |
| **Gateway/Request/CaptureDataBuilder.php** | `action: capture`, `transaction_id` (from the previous authorization). |
| **Gateway/Request/RefundDataBuilder.php** | `action: refund`, `transaction_id`, `refund_amount`. |
| **Gateway/Request/VoidDataBuilder.php** | `action: void`, `transaction_id`. |
| **Gateway/Response/TransactionIdHandler.php** | Parses `transaction_id` from the API response and stores it with `Payment->setTransactionId()`. |
| **Gateway/Response/PaymentDetailsHandler.php** | Stores additional details from the API response (`status`, `process_id`, etc.) with `Payment->setAdditionalInformation()`. |
| **Gateway/Validator/GeneralResponseValidator.php** | Extension of `AbstractValidator`. Checks the HTTP status code, `success` flag, and SanalPosPro-specific error states. |
| **Observer/DataAssignObserver.php** | Extension of `AbstractDataAssignObserver`. Maps `additional_data` from the frontend to `PaymentInfo.additionalInformation`. Allowed keys: `paythor_token`, `payment_method`. |
| **Model/Ui/ConfigProvider.php** | `ConfigProviderInterface` implementation. Provides `isActive`, `title`, `isSandbox`, and `paymentAction` data under `window.checkoutConfig.payment.paythor_sanalpospro`. |
| **Model/Api/PaythorAdapter.php** | Bridge methods were added to the existing adapter: `createPaymentFromGateway()`, `captureFromGateway()`, `refundFromGateway()`, `voidFromGateway()`, `normalizeApiResponse()`. |
| **etc/di.xml** | Full Gateway Facade configuration: `PaythorSanalPosProFacade` (VirtualType -> `Magento\Payment\Model\Method\Adapter`), `CommandPool`, `ValueHandlerPool`, `ValidatorPool`, `CountryValidator`, `BuilderComposite` classes, handler chains, and Logger DI. Sensitive configuration via `TypePool`. |
| **etc/events.xml** | Registers `payment_method_assign_data_paythor_sanalpospro` -> `DataAssignObserver`. |
| **etc/frontend/di.xml** | Registers `ConfigProvider` -> `Magento\Checkout\Model\CompositeConfigProvider`. |
| **etc/config.xml** | Updated the `model` value to the `PaythorSanalPosProFacade` VirtualType. |

### Technical Decisions
- The Gateway API layer was wrapped with the **bridge pattern** without breaking the existing `PaythorAdapter` SDK logic. `PaythorGatewayClient` does not make direct HTTP calls; it delegates the operation to the adapter.
- `ObjectManager` was not used anywhere; all dependencies were injected through DI with Constructor Injection.
- `GeneralResponseValidator` handles both general HTTP errors and SanalPosPro API-specific error codes.
- Handler classes were kept simple: they only assign data and do not contain business logic (`.cursorrules` rule).

### Verification
```bash
bin/magento setup:di:compile   # DI configuration validation
bin/magento setup:upgrade      # Module and DB schema validation
```

---

## Phase 3 - Frontend Checkout (Knockout.js / UI Components)

### Goal
Complete the rendering of the payment method as a Knockout.js UI Component on the checkout page, the `getData()` flow integrated with the Gateway API, the modal-based secure payment window, and modern CSS styles.

### Payment Flow (UX)

```
1. Customer clicks "Place Order"
   │
2. setPaymentInformationAction() -> additional_data is sent to the backend
   │   ├─ paythor_token (if present)
   │   └─ payment_method: 'credit_card'
   │
3. AJAX POST -> paythor/payment/create
   │   -> Backend CreateController returns iframe HTML
   │
4. Modal opens -> SanalPosPro iframe is displayed
   │   -> Customer enters card details in SanalPosPro's secure form
   │
5a. postMessage (SanalPosPro iframe -> parent window)
   │   ├─ Success: _sendConfirmRequest() -> order is created -> redirect
   │   └─ Failure: modal closes, error message is shown, cart is preserved
   │
5b. Callback bridge (SanalPosPro redirect -> Callback.php -> postMessage)
       └─ Same flow
```

### Created / Updated Files

| File | Description |
|---|---|
| **view/frontend/web/js/view/payment/sanalpospro.js** | Renderer-list registration. Maps the `paythor_sanalpospro` code to the `sanalpospro-method.js` component. (No change; correct as-is.) |
| **view/frontend/web/js/view/payment/method-renderer/sanalpospro-method.js** | **Updated.** `getData()` was made compatible with `DataAssignObserver` using `additional_data: { paythor_token, payment_method }`. `ConfigProvider` data is read through `window.checkoutConfig.payment.paythor_sanalpospro`. Added `isActive()` and `isSandbox()` methods. The full `getData()` object is sent to `setPaymentInformationAction()`. |
| **view/frontend/web/template/payment/sanalpospro.html** | **Updated.** Automatic **TEST** badge display in sandbox mode (`isSandbox()`). Card icons are referenced with the correct namespace. Added security information text. |
| **view/frontend/web/css/paythor-checkout.css** | **New.** Modern modal styling (gradient header, rounded corners, shadow), sandbox badge, information box, security note, and responsive behavior (full-screen modal on mobile). |
| **view/frontend/requirejs-config.js** | **New.** RequireJS module mapping configuration. |
| **view/frontend/layout/checkout_index_index.xml** | **Updated.** Added a `Paythor_SanalPosPro::css/paythor-checkout.css` reference to the `<head>` block. |

### Frontend -> Backend Data Flow

```
sanalpospro-method.js
  getData() -> { method: 'paythor_sanalpospro', additional_data: { paythor_token, payment_method } }
    │
    ▼
Magento setPaymentInformationAction()
    │
    ▼
events.xml -> DataAssignObserver.execute()
    │  PaymentInfo.setAdditionalInformation('paythor_token', ...)
    │  PaymentInfo.setAdditionalInformation('payment_method', ...)
    │
    ▼
Gateway CommandPool -> Request Builders -> PaythorGatewayClient -> PaythorAdapter
```

### ConfigProvider -> Frontend Mapping

| ConfigProvider (PHP) | JS Access | Usage |
|---|---|---|
| `isActive` | `config.isActive` | `isActive()` method |
| `title` | `config.title` | `getTitle()` (Magento default) |
| `isSandbox` | `config.isSandbox` | `isSandbox()` -> TEST badge |
| `paymentAction` | `config.paymentAction` | Future payment flow branching |

### Technical Decisions
- Because the payment form is iframe/redirect-based (card details are not collected in Magento), `Magento_Checkout/js/view/payment/default` was extended instead of `Magento_Payment/js/view/payment/cc-form`. Although `.cursorrules` recommends `cc-form`, `default` is the correct choice for this architecture.
- `redirectAfterPlaceOrder: false` -> Magento's default redirect is disabled; the flow is managed entirely through custom AJAX.
- When the modal closes (`closed` callback), the cart is preserved so the customer can try again.
- The PostMessage listener listens to two sources: (A) directly from the SanalPosPro iframe, and (B) from the Callback.php bridge.

### Verification
```bash
bin/magento setup:di:compile
bin/magento setup:upgrade --keep-generated
bin/magento cache:flush
```

---

## Full Module File Map

```
src/app/code/Paythor/SanalPosPro/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── config.xml
│   ├── di.xml
│   ├── events.xml
│   ├── acl.xml
│   ├── db_schema.xml
│   ├── db_schema_whitelist.json
│   ├── adminhtml/
│   │   └── system.xml
│   └── frontend/
│       └── di.xml
├── Gateway/
│   ├── Http/
│   │   ├── TransferFactory.php
│   │   └── Client/
│   │       └── PaythorGatewayClient.php
│   ├── Request/
│   │   ├── PaymentDataBuilder.php
│   │   ├── AuthorizationDataBuilder.php
│   │   ├── CaptureDataBuilder.php
│   │   ├── RefundDataBuilder.php
│   │   └── VoidDataBuilder.php
│   ├── Response/
│   │   ├── TransactionIdHandler.php
│   │   └── PaymentDetailsHandler.php
│   └── Validator/
│       └── GeneralResponseValidator.php
├── Model/
│   ├── Api/
│   │   └── PaythorAdapter.php          <- bridge methods added
│   ├── Config/
│   │   └── PaymentConfig.php
│   └── Ui/
│       └── ConfigProvider.php
├── Observer/
│   └── DataAssignObserver.php
└── view/
    └── frontend/
        ├── layout/
        │   └── checkout_index_index.xml
        ├── requirejs-config.js
        └── web/
            ├── css/
            │   └── paythor-checkout.css
            ├── images/
            │   └── cards/  (PNG icons)
            ├── js/
            │   └── view/payment/
            │       ├── sanalpospro.js
            │       └── method-renderer/
            │           └── sanalpospro-method.js
            └── template/payment/
                └── sanalpospro.html
```

---

## Applied Magento 2 Best Practices

| Rule | Implementation |
|---|---|
| ObjectManager usage prohibited | All classes use DI with Constructor Injection |
| Declarative Schema | `db_schema.xml` + whitelist; no `setup_version` |
| Gateway API | `Magento\Payment\Model\Method\Adapter` VirtualType Facade |
| Sensitive data protection | `TypePool` -> `environment` (written to env.php) |
| Business logic prohibited in observers | Observer only assigns data; logic is in the adapter |
| Handler simplicity | Only updates the Payment object |
| Raw SQL prohibited | Repository pattern and Collection usage |
| after/before instead of around plugin | No plugin was used; DI preference and observer were preferred |
| Core code modification prohibited | Fully separate module; no preference/plugin |

<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(title: "Shopify Conectos API", version: "1.0.0")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter your bearer token in the format **Bearer &lt;token&gt;**"
)]
class OpenApiSpec {}

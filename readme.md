### Local development

Create `index.php` file for local debug

```php

require('vendor/autoload.php');


$labels = [
    'app.kubernetes.io/component' => 'worker',
    'app.kubernetes.io/instance' => 'helm',
];

$api = new ApiClient();
$api->setApiUrl('http://localhost:8080');
$api->setNamespace('helm');
$api->setMode(ApiClient::DEV_MODE);

```

Run k8s proxy 
```
kubectl proxy --port=8080
```

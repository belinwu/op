# Op

A simple web framework for PHP.

框架的名字取自于《海贼王(One Piece)》这部动漫。

该框架仍在开发当中，目前处于内测阶段。(This framework is in Alpha for now.)

### Document

Comming soon...

### Demo

```php
<?php
require '/path/to/op.php';

op::get('/', function () {
    op::render('home.php');
});

op::run();
?>
```

### Example

See [My Personal Blog](https://github.com/belinwu/blog)
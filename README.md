# ErrorBag

Все языки программирования отталкиваются от исключений в качестве сбора ошибок.  
Сильнейший плюс исключений в том, что исключение останавливает работу программы. В этом же сильнейший минус.

Бывает что вы хотите остановить не все логику программы, а только её часть.  
Если в логике выпадет исключение, она остановится целиком.  
Вы не сможете вернуться внутрь прерванной исключением функции, чтобы принять меры и продолжить её выполнение, она для вас потеряна.  
Это заставит вас дробить ваши функции на микроскопические до такой степени, что у вас будет больше функций, чем кода.

Главная задача инструмента: собрать ошибки в единый объект, чтобы вывести отчет в API или лог


## Установка

```
composer require gzhegow/error-bag;
```

## Пример

```php
<?php

use Gzhegow\ErrorBag\Lib;
use Gzhegow\ErrorBag\ErrorBagStack;
use function Gzhegow\ErrorBag\_error_bag;
use function Gzhegow\ErrorBag\_error_bag_pop;
use function Gzhegow\ErrorBag\_error_bag_end;
use function Gzhegow\ErrorBag\_error_bag_push;
use function Gzhegow\ErrorBag\_error_bag_start;


require_once __DIR__ . '/vendor/autoload.php';

// > подключение файла хелперов не обязательно, используется в демонстрационных целях
require_once __DIR__ . '/helpers/error-bag.example.php';


// > настраиваем PHP
ini_set('memory_limit', '32M');

// > настраиваем обработку ошибок
error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (error_reporting() & $errno) {
        throw new \ErrorException($errstr, -1, $errno, $errfile, $errline);
    }
});
set_exception_handler(function ($e) {
    var_dump(Lib::php_dump($e));
    var_dump($e->getMessage());
    var_dump(($e->getFile() ?? '{file}') . ': ' . ($e->getLine() ?? '{line}'));

    die();
});


function a()
{
    // > получаем текущий error-bag
    _error_bag($b);

    // > создаем дочерний error-bag, который будет отвечать за функцию aa()
    _error_bag_push($bb);

    $result = aa();

    // > завершаем дочерний error-bag (опционально - передаем его самого для проверки, не забыли ли глубже закрыть другие)
    _error_bag_pop($bb);

    // > соединяем закрытый в указанный с присвоением пути и тегов
    $b->merge($bb, 'aa', 'tag_aa');

    // > то же самое, только объединение произойдет в актуальный (текущий)
    // _error_bag_merge($bb, 'aa', 'tag_aa');

    return $result;
}

function aa()
{
    _error_bag($b);

    $result = [];

    for ( $i = 0; $i <= 5; $i++ ) {
        _error_bag_push($bb);

        $_result = aaa();

        _error_bag_pop($bb);

        // > соединяем закрытый в указанный как предупреждения (если ошибка была решена) с присвоением пути и тегов
        $b->message($bb, [ 'aaa', $i ], 'tag_aaa');

        // то же самое, только в актуальный (текущий)
        // _error_bag_message($bb, [ 'aaa', $i ], 'tag_aaa');

        // > если во вложенном были ошибки - элемент пропускаем (принятие решение в родителе в зависимости от потомка)
        if ($bb->hasErrors()) {
            continue;
        }

        $result[] = $_result;
    }

    return $result;
}

function aaa()
{
    _error_bag($b);

    $result = [];

    for ( $i = 0; $i <= 5; $i++ ) {
        _error_bag_push($bb);

        $_result = aaaa($i);

        _error_bag_pop($bb);

        $b->message($bb, [ 'aaaa', $i ], 'tag_aaaa');
        if ($bb->hasErrors()) {
            continue;
        }

        $result[] = $_result;
    }

    return $result;
}

function aaaa($i)
{
    _error_bag($b);

    if ($i === 1) {
        // > добавляем ошибку, можно указать путь и теги
        $b->error("Error {$i}", $path = null, $tags = 'tag1'); // 1

    } elseif ($i % 2) {
        $b->error("Error {$i}", null, 'tag2'); // 2, 4

    } elseif ($i % 3) {
        // > добавляем предупреждение, можно указать путь и теги
        $b->message("Message {$i}", null, 'tag3'); // 3
    }

    // > принимаем решение в текущей функции, если нужно
    // if (! $b->hasErrors()) {
    // if (! $b->hasMessages()) {
    if (! $b->isEmpty()) {
        return null;
    }

    return $i;
}


// > включаем отлов ошибок
_error_bag_start($b);

$result = a();
var_dump($result); // > какой-то результат вашей логики

// > завершаем отлов ошибок, иначе дальнейший код продолжит отлавливать в открытый ранее error-bag
_error_bag_end($b);

// > все проблемы вложенным массивом
// var_dump($b->toArrayNested($asObject = true));

// > все проблемы массивом
var_dump($b->toArray($implodeKeySeparator = '|'));
// var_dump($b->getErrors()->toArray('|')); // > все ошибки массивом
// var_dump($b->getMessages()->toArray('|')); // > все сообщения массивом


$stack = ErrorBagStack::getInstance();
if (! (null === $stack->hasErrorBag())) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;

$bb = $b->getByTags($andTags = [ 'tag_aaa', 'tag1' ]);
if (! (6 === count($bb))) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;

$bb = $b->getByTags($tag = 'tag1', $orTag = 'tag2');
if (! (18 === count($bb))) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;

$bb = $b->getByTags($andTags = [ 'tag1', 'tag2' ]);
if (! (0 === count($bb))) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;

$bb = $b->getByTags($andTags = (object) [ 'tag_aaa', 'tag1' ], $orAndTags = (object) [ 'tag_aaa', 'tag2' ]);
if (! (18 === count($bb))) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;


$bb = $b->getByPath($path = [ 'aaa', 1 ]);
if (! (5 === count($bb))) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;

$bb = $b->getByPath($path = [ 'aaa', 1 ], $orPath = [ 'aaa', 2 ]);
if (! (10 === count($bb))) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;

$bb = $b->getByPath($andPathes = (object) [ [ 'aaa', 1 ], [ 'aaa', 2 ] ]);
if (! (0 === count($bb))) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;

$bb = $b->getByPath($andPathes = (object) [ [ 'aaa', 1 ], [ 'aaaa', 1 ] ], $orAndPathes = (object) [ [ 'aaa', 1 ], [ 'aaaa', 2 ] ]);
if (! (2 === count($bb))) throw new \RuntimeException();
echo 'Test OK' . PHP_EOL;
```

### Дополнительные задачи:

1) соединить ошибки (или часть ошибок) из одной операции с ошибками из другой

```
Например, вы хотите отослать 10 телефонов, но из них каждый идет по 2 раза, то есть телефонов по сути 5. Вы отошлете 5 записей и ошибок будет 5.  
Но исходные данные предполагают, что их было 10. Вам потребуется копировать ошибки по 2 раза, чтобы сохранить их в очередь каждые в свою ячейку
Очень удобно выполняя массовую операцию присвоить группе ошибок тег, а далее при дублировании по ячейкам искать по этому тегу.
```

2) уменьшить количество классов и время выполнения при обработке ошибок

```
Чтобы выстрелить исключением - вы должны создать класс (опционально, привязать к нему интерфейс) и потом по этому искать в блоке try/catch
Проблемы try/catch:
а) Присвоение типа ошибки в месте, где её создали, не имеет смысла, но несколько ошибок одного типа могут стать важны в родительской функции управления
б) Присовение типа на месте - это то же самое, что присвоить числовой код, только требует еще и класс для этого создавать
```

3) Принимать решение на месте по наличию ошибок и помечать их обработанными

```
Старый добрый `if (count($errors)) return null;`
Одна беда. Ошибки нижнего уровня не всегда ошибки для верхнего, но они никуда не исчезали для отчета об операции.
Инструмент позволяет превратить ошибки в предупреждения, чтобы в управляющей функции проверка прошла, а в дочерней - нет.
```

4) Позволяет собирать по частям путь возникновения ошибки

```
Вот эта задача с которой исключения справляются на отлично - они собирают стек-трейс.  
Одна проблема. Сбор стек трейса занимает время. Вы теряете время на сбор ненужных трейсов, которые вы обработаете.
Трейсы нужны только если ошибка доберется до самого верха, то есть останется не пойманной.
```

5) Не трогать старый код и не менять сигнатуру функций и методов

```
Чтобы собирать ошибки в виде массивов - самое болючее - это тянуть наверх массив с собранными ошибками и менять выходные/входные типы для возврата этих ошибок.
Этот инструмент работает глобально и менять ничего не придется.
```

### По поводу "экономии памяти"

Сначала я реализовывал его так, чтобы его можно было выключить в нужный момент, чтобы экономить память.  
Однако точки принятия решений hasErrors() будут тогда возвращать что ошибок нет, а значит программа сделает то, что не должна.

### По поводу "стека"

Если ваша программа бросает исключения, которые потом вы отловили, вы могли пропустить вызов _error_bag_pop()  
Привяжите set_error_handler/set_exception_handler так, чтобы он завершал начатые error_bag() до некоторого уровня или полностью.  
Или не забывайте это делать вручную в try/catch. Для этого и сделана проверка _error_bag_pop(verify), которая проверяет правильность error_bag и бросает ошибку, если туда попал не тот, что сейчас последний в стеке.

### По поводу "дисклеймера"

Обычно, когда я предлагаю идею в PHP сообщество, проходит 2 года и потом её внедряют как чью-то ещё. Сегодня 10.02.2024.
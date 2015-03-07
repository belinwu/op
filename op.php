<?php

namespace {
    /**
     * The op class is a static representation of the One Piece framework.
     * @package \
     */
    final class op {
        /**
         * The framework instance.
         * @var \op\App.
         */
        private static $app;
        /**
         * Prevents the op class from being initialized.
         */
        private function __construct() {}
        private function __destruct() {}
        private function __clone() {}

        public static function __callStatic($name, $params) {
            static $initialized = false;
            if (!$initialized) {
                static::$app = new op\App();
                $initialized = true;
            }
            return call_user_func_array([static::$app, $name], $params);
        }        

        public static function start_with($str, $needle) {
            return $needle === '' || substr($str, 0, strlen($needle)) === $needle;
        }

        public static function end_with($str, $needle) {
            return $needle === '' || substr($str, -strlen($needle)) === $needle;
        }
    }
}

namespace op {
    /**
     * The App class contains all functionality of the framework..
     * @package \op
     */
    class App {
        /**
         * @var Dispatcher The dispatcher.
         */
        private $dispatcher;

        /**
         * @var Router The router.
         */
        private $router;

        /**
         * @var Arrays The attributes.
         */
        private $attrs;

        /**
         * @var Evenement The event manager.
         */
        private $evenement;

        /**
         * @var Crude The database tool kit.
         */
        private $crude;

        /**
         * @var Pure The container.
         */
        private $pure;

        /**
         * Inits the framework.
         */
        public function __construct() {
            $this->dispatcher = new Dispatcher();
            $this->pure       = new Pure();
            $this->evenement  = new Evenement();
            $this->router     = new Router();
            $this->attrs      = new Arrays();
            
            $this->pure['op.request_class']  = '\op\Request';
            $this->pure['op.response_class'] = '\op\Response';

            $this->pure->define('op.request', function ($pure) {
                return new $pure['op.request_class']();
            })->define('op.response', function ($pure) {
                return new $pure['op.response_class']();
            });
        }

        /**
         * Runs the op framework.
         */
        public function run() {
            if (!\op::attr('op.debug')) {
                set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                    if ($errno & error_reporting()) {
                        throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
                    }
                });
                set_exception_handler(function (\Exception $e) {
                    $this->error(500);
                });
            }
            if ($route = $this->router->route($this->pure['op.request'])) {
                ob_start();
                $this->dispatcher->dispatch($route);
                $this->pure['op.response']->write(ob_get_clean())->send();
            } else {
                $this->error(404);
            }
        }

        /**
         * Access the attributes of Puree framework.
         * @param string $name  The name of attribute.
         * @param mixed  $value The value of attribute.
         * @return mixed
         */
        public function attr($name = null, $value = null) {
            if (is_null($name)) { 
                return $this->attrs; 
            }
            if (is_null($value)) {
                return isset($this->attrs[$name]) ? $this->attrs[$name] : null;
            } else {
                if (is_array($name)) {
                    foreach ($name as $k => $v) {
                        $this->attrs[$k] = $v;
                    }
                } else {
                    $this->attrs[$name] = $value;
                }
            }
        }

        public function session($name = null, $value = null) {
            static $active = false;
            if (!$active) {
                session_start();
                $active = true;
            }
            if (is_null($name)) {
                session_unset();
                session_destroy();
            }
            if (is_null($value)) {
                return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
            } else {
                if (is_array($name)) {
                    foreach ($name as $k => $v) {
                        $_SESSION[$k] = $v;
                    }
                } else {
                    $_SESSION[$name] = $value;
                }
            }
        }

        /**
         * Adds a route.
         * @param string   $pattern The pattern of route.
         * @param callable $handler The handler of route.
         * @return op\Route The route.
         */
        public function route($pattern, callable $handler) {
            return $this->router->add_route($pattern, $handler);
        }

        /**
         * Adds a GET route.
         * @param string   $pattern The pattern of route.
         * @param callable $handler The handler of route.
         * @return op\Route The route.
         */
        public function get($pattern, callable $handler) {
            return $this->route($pattern, $handler)->method('GET');
        }

        /**
         * Adds a POST route.
         * @param string   $pattern The pattern of route.
         * @param callable $handler The handler of route.
         * @return op\Route The route.
         */
        public function post($pattern, callable $handler) {
            return $this->route($pattern, $handler)->method('POST');
        }

        /**
         * Adds a PUT route.
         * @param string   $pattern The pattern of route.
         * @param callable $handler The handler of route.
         * @return op\Route The route.
         */
        public function put($pattern, callable $handler) {
            return $this->route($pattern, $handler)->method('PUT');
        }

        /**
         * Adds a DELETE route.
         * @param string   $pattern The pattern of route.
         * @param callable $handler The handler of route.
         * @return op\Route The route.
         */
        public function delete($pattern, callable $handler) {
            return $this->route($pattern, $handler)->method('DELETE');
        }

        /**
         * Groups the routes.
         * @param callable $handler The group handler.
         * @param array    $group   The group configurations.
         */
        public function group(callable $handler, array $group = []) {
            $this->router->group($group);
            call_user_func($handler);
            $this->router->group([]);
        }

        public function error($status, array $data = []) {
            $view = \op::attr('op.' . $status);
            $output = isset($view) ? $this->render($view) : 
                ($status == 404 ? '404 Page Not Found' : '500 Internal Server Error');
            $this->pure->load('op.response', false)
                ->status($status)
                ->write($output)
                ->send();
        }

        public function is_set($var) {
            return isset($var);
        }

        /**
         * Adds a filter.
         * @param string   $name    The name of filter.
         * @param callable $handler The handler of filter.
         */
        public function filter($name, callable $handler) {
            $this->dispatcher->add_filter($name, $handler);
        }

        /**
         * Apply before type filter to the target.
         * @param string $filtereds The target to apply.
         * @param string $filter    The name of filter.
         * @param string $methods   The HTTP request methods.
         */
        public function before($filtereds, $filter, $methods = '*') {
            $this->dispatcher->apply_filter('before', $filtereds, $filter, $methods);
        }

        /**
         * Apply after type filter to the target.
         * @param string $filtereds The target to apply.
         * @param string $filter    The name of filter.
         * @param string $methods   The HTTP request methods.
         */
        public function after($filtereds, $filter, $methods = '*') {
            $this->dispatcher->apply_filter('after', $filtereds, $filter, $methods);
        }

        /**
         * Apply before type filter to the target.
         * @param string $filtereds The target to apply.
         * @param string $filter    The name of filter.
         * @param string $methods   The HTTP request methods.
         */
        public function when($filtereds, $filter, $methods = '*') {
            $this->dispatcher->apply_filter('before', $filtereds, $filter, $methods);
        }

        /**
         * Gets the request parameter.
         * @param string|null $key The key of parameter or null.
         * @return mixed The parameter. If $key is null, return all parameters.
         */
        public function param($key = null) {
            return $this->pure['op.request']->param($key);
        }

        /**
         * Creates a Arrays object.
         * @param array $datas A array of datas for initial.
         * @return op\Arrays A Arrays object.
         */
        public function arrays(array $datas) {
            return new Arrays($datas);
        }

        /**
         * Renders the view.
         * @param string      $file  The filename of view.
         * @param array       $datas The datas of view.
         * @param string|null $key   The key of view as a layout.
         */
        public function render($file, array $datas = []) {
            $file_path = \op::attr('op.views') . $file;
            if (!file_exists($file_path)) {
                throw new \Exception('Can not find the template file: ' . $file_path);
            }
            ob_start();
            extract($datas);
            include $file_path;
            $output = ob_get_clean();
            $this->pure['op.response']->header('Content-type', 'text/html;charset=utf-8')->write($output)->send();
        }

        public function upload($key) {

        }

        public function download($file) {

        }

        public function redirect($url, $status = 302) {
            $this->pure->load('op.response', false)->status($status)->header('Location', $url)->write($output)->send();
        }

        public function forward($url) {
            $this->pure['op.request']->url($url);
            $this->run();
        }

        /**
         * Creates a JSON response.
         * @param array $datas The datas of response.
         */
        public function json($data, $func = null) {
            $headers = [
                'Content-type'  => 'application/' . (is_null($func) ? 'json' : 'javascript'),
                'Expires'       => 'Mon, 26 Jul 1997 05:00:00 GMT',
                'Pragma'        => 'no-cache',
                'Cache-Control' => [
                    'no-store, no-cache, must-revalidate',
                    'post-check=0, pre-check=0',
                    'max-age=0'
                ]
            ];
            $output = is_null($func) ? json_encode($data) : ";{$func}(".json_encode($data).");";
            $this->pure->load('op.response', false)->header($headers)->write($output)->send();
        }

        public function plan($text) {
            $this->pure->load('op.response', false)->header('Content-Type', 'text/plain;charset=utf-8')->write($text)->send();   
        }

        /**
         *
         * @param string   $event   The name of event.
         * @param callable $handler The handler of event.
         * @param array    $params  The parameters of listener.
         */
        public function on($event, callable $handler, array $params = []) {
            $this->evenement->on($event, $handler, $params);
        }

        /**
         *
         * @param string   $event   The name of event.
         * @param callable $handler The handler of event.
         * @param array    $params  The parameters of listener.
         */
        public function once($event, callable $handler, array $params = []) {
            $this->evenement->once($event, $handler, $params);
        }

        /**
         * 触发执行监听器。
         * @param string $event  The name of event.
         * @param array  $params
         */
        public function fire($event, array $params = []) {
            $this->evenement->fire($event, $params);
        }

        /**
         *
         * @param string   $event   The name of event.
         * @param callable $handler The handler of event.
         */
        public function off($event, callable $handler) {
            $this->evenement->off($event, $handler);
        }

        public function extend($name, callable $handler = null) {
            if (method_exists('\op\App', $name) || 
                method_exists('\op\Crude', $name) || 
                method_exists('\op', $name)) {
                throw new \Exception('框架方法名已存在，不能重复定义。');
            }
            $this->protect($name, $handler);
        }

        public function protect($id, callable $handler) {
            $this->pure->$id = $this->pure->protect($handler);
        }

        public function share($id, callable $handler) {
            $this->pure->$id = $this->pure->share($handler);
        }

        public function decorate($id, callable $handler) {
            $this->pure->decorate($id, $handler);
        }

        public function renew($id) {
            return $this->pure->renew($id);
        }

        public function define($id, $value) {
            return $this->pure->define($id, $value);
        }

        public function undefine($id) {
            return $this->pure->undefine($id);
        }

        public function object($id, $object = null) {
            if (is_array($id) && !isset($id[0])) {
                $this->pure->set($id);
            } else {
                if (is_null($object)) { 
                    return $this->pure->get($id); 
                }
                $this->pure->set($id, $object);
            }
            return $this->pure;
        }

        public function raw($id) {
            return $this->pure->raw($id);
        }

        public function __call($name, $params) {
            # call crude methods.
            if (method_exists('\op\Crude', $name)) {
                if (is_null($this->crude)) {
                    $db = \op::attr('db');
                    if (!isset($db)) { 
                        throw new \Exception('数据库连接参数未配置，无法进行数据库操作！');
                    }
                    $this->crude = new Crude($db->to_array());
                }
                return call_user_func_array([$this->crude, $name], $params); 
            }
            # call extended methods.
            $handler = $this->pure->$name;
            if (isset($handler)) {
                return call_user_func_array($handler, $params);  
            }
        }
    }

    /**
     * The Pure class represents container.
     * @package op
     */
    class Pure implements \ArrayAccess {
        /**
         * @var array The values. Key is ID, value is value.
         */
        private $values = [];

        /**
         * @var array The objects. Key is ID, value is object.
         */
        private $objects = [];

        public function offsetGet($id) {
            return isset($this->objects[$id]) 
                ? $this->objects[$id] : (isset($this->values[$id]) 
                    ? ($this->objects[$id] = $this->values[$id]($this)) : null);
        }

        public function offsetSet($id, $value) {
            $this->objects[$id] = $value;
        }

        public function offsetExists($id) {
            return isset($this->objects[$id]);
        }

        public function offsetUnset($id) {
            unset($this->objects[$id]);
        }

        public function __get($id) {
            if (!isset($this->values[$id])) { return null; }
            $value = $this->values[$id];
            return is_callable($value) ? $value($this) : $value;
        }

        public function __set($id, $value) {
            if (!is_null($id)) {
                $this->values[$id] = $value;
                unset($this->objects[$id]);
            }
        }

        public function __isset($id) {
            return isset($this->values[$id]);
        }

        public function __unset($id) {
            unset($this->values[$id]);
            unset($this->objects[$id]);
        }

        public function renew($id) {
            $value = $this->values[$id];
            if (isset($value) && is_callable($value)) {
                return $this->objects[$id] = $value($this);
            }
        }

        public function define($id, $value) {
            $this->__set($id, $value);
            return $this;
        }

        public function undefine($id) {
            $this->__unset($id);
            return $this;
        }

        /**
         * Gets object from container.
         * @param string $id     The id of object.
         * @param bool   $shared
         * @return mixed|null
         */
        public function load($id, $shared = true) {
            return $shared ? $this->offsetGet($id) : $this->renew($id);
        }

        /**
         * Gets the object by id.
         * @param string $id The object id.
         * @return mixed|null The object or null.
         */
        public function get($id) {
            if (is_array($id)) {
                $objects = [];
                foreach ($id as $item) {
                    $objects[] = $this->offsetGet($item);
                }
                return $objects;
            }
            return $this->offsetGet($id);
        }

        /**
         * Sets the object by id.
         * @param string $id The object id.
         * @param mixed $object The object
         */
        public function set($id, $object = null) {
            if (is_array($id)) {
                foreach ($id as $key => $value) {
                    $this->offsetSet($key, $value);
                }
            } else {
                if (is_null($object)) { 
                    return; 
                }
                $this->offsetSet($id, $object);
            }
        }

        /**
         * @param callable $callable 定义。
         * @return callable|null 定义。
         */
        public function share(callable $callable) {
            return function ($pure) use ($callable) {
                static $bean;
                if ($bean === null) { 
                    $bean = $callable($pure); 
                }
                return $bean;
            };
        }

        /**
         * @param string $id 定义的ID。
         * @return mixed|null 定义。
         */
        public function raw($id) {
            return isset($this->values[$id]) ? $this->values[$id] : null;
        }

        /**
         * @param string   $id       定义的ID。
         * @param callable $callable 扩展体。
         * @return callable|null 新的定义。
         */
        public function decorate($id, callable $callable) {
            if (!isset($this->values[$id]) || 
                !is_callable($this->values[$id])) { 
                return null; 
            }
            $value = $this->values[$id];
            return $this[$id] = function ($pure) use ($callable, $value) {
                return $callable($value($pure), $pure);
            };
        }

        public function protect(callable $handler) {
            return function () use ($handler) { 
                return $handler; 
            };
        }
    }

    /**
     * The Arrays class.
     * @package op
     */
    class Arrays implements \ArrayAccess, \Iterator, \Countable {
        private $datas = [];

        public function __construct(array $datas = []) {
            foreach ($datas as $key => $value) {
                $this[$key] = $value;
            }
        }

        public function count() {
            return count($this->datas);
        }

        public function offsetGet($offset) {
            $offset = \op::start_with($offset, '_') ? $offset : str_replace('_', '.', $offset);
            $a      = &$this->datas;
            foreach (explode('.', $offset) as $key) {
                if (is_int($key)) { 
                    $key = (int) $key; 
                }
                if (!isset($a[$key])) { 
                    return null; 
                }
                $a = &$a[$key];
            }
            return is_array($a) ? new Arrays($a) : $a;
        }

        public function offsetSet($offset, $value) {
            if (is_null($offset)) {
                $this->datas[] = $value;
            } else {
                $offset = \op::start_with($offset, '_') ? $offset : str_replace('_', '.', $offset);
                $a      = &$this->datas;
                foreach (explode('.', $offset) as $key) {
                    if (is_int($key)) { 
                        $key = (int) $key; 
                    }
                    if (!isset($a[$key])) { 
                        $a[$key] = null; 
                    }
                    $a = &$a[$key];
                }
                $a = $value;
            }
        }

        public function offsetExists($offset) {
            return $this->__isset($offset);
        }

        public function offsetUnset($offset) {
            $this->__unset($offset);
        }

        public function __get($name) {
            return $this->offsetGet($name);
        }

        public function __set($name, $value) {
            return $this->offsetSet($name, $value);
        }

        public function __isset($name) {
            $data = &$this->datas;
            if (\op::start_with($name, '_')) {
                return isset($this->datas[$name]);
            }
            foreach (explode('.', str_replace('_', '.', $name)) as $key) {
                if (is_int($key)) { 
                    $key = (int) $key; 
                }
                if (!isset($data[$key])) {
                    return false;
                }
                $data = &$data[$key];
            }
            return true;
        }

        public function __unset($name) {
            $data = &$this->datas;
            if (\op::start_with($name, '_')) {
                unset($this->datas[$name]);
                return;
            }
            foreach (explode('.', str_replace('_', '.', $name)) as $key) {
                if (is_int($key)) { 
                    $key = (int) $key; 
                }
                if (!isset($data[$key])) {
                    return false;
                }
                $data = &$data[$key];
            }
            unset($data);
        }

        public function pop($name) {
            if (isset($this->datas[$name])) {
                $data = $this->datas[$name];
                unset($this->datas[$name]);
                return $data;
            }
        }

        public function rewind() {
            reset($this->datas);
        }

        public function current() {
            return \op::arrays(current($this->datas));
        }

        public function key() {
            return key($this->datas);
        }

        public function next() {
            return next($this->datas);
        }

        public function valid() {
            $key = key($this->datas);
            return $key !== null && $key !== false;
        }

        public function to_array() {
            return $this->datas;
        }
    }

    /**
     * The Dispatcher class.
     * @package op
     */
    class Dispatcher {
        /**
         * @var The filters applied.<br />
         * <pre>
         * [
         *     'name' => [
         *         'route name' => [
         *             'before' => ['fitler name', 'fitler name', 'fitler name', ...],
         *             'after'  => ['fitler name', 'fitler name', 'fitler name', ...]
         *          ]
         *     ],
         *     'pattern' => [
         *         'route pattern' => [
         *             'before' => ['fitler name', 'fitler name', 'fitler name', ...],
         *             'after'  => ['fitler name', 'fitler name', 'fitler name', ...]
         *         ]
         *     ]
         * ]
         * </pre>
         */
        private $applied_filters = [ 'name' => [], 'pattern' => [] ];

        /**
         * The filters. Key is name of filter, values is handler of filter.
         * @var array
         */
        private $filters = [];

        /**
         * Dispatch the current route.
         * @param Route $route The current route.
         */
        public function dispatch(Route $route) {
            $route_name = $route->name();
            if (!isset($this->applied_filters['name'][$route_name])) {
                $this->applied_filters['name'][$route_name]           = [];
                $this->applied_filters['name'][$route_name]['before'] = [];
                $this->applied_filters['name'][$route_name]['after']  = [];
            }
            foreach ($this->applied_filters['pattern'] as $pattern => $pattern_filters) {
                if (preg_match('#^' . $pattern . '$#', $route->url())) {
                    foreach ($pattern_filters as $type => $filters) {
                        $old_filters = $this->applied_filters['name'][$route_name][$type];
                        $this->applied_filters['name'][$route_name][$type] = array_merge($filters, $old_filters);
                    }
                }
            }
            if ($this->call_filter($route_name, 'before')) {
                call_user_func_array($route->handler(), array_values($route->params()));
                $this->call_filter($route_name, 'after');
            }
        }

        /**
         * Adds filter.
         * @param string   $name   The name of filter.
         * @param callable $handler The handler of filter.
         */
        public function add_filter($name, callable $handler) {
            $this->filters[$name] = $handler;
        }

        /**
         * Applies filter.
         * @param string          $type      The type of filter.
         * @param string          $filtereds The target to apply.
         * @param string|callable $filter    The name or handler of filter.
         * @param array           $methods   The HTTP request methods.
         */
        public function apply_filter($type, $filtereds, $filter, $methods) {
            if (!is_string($filtereds)) { 
                return; 
            }
            $by = (strpos($filtereds, '/') !== false) ? 'pattern' : 'name';
            foreach (explode('|', str_replace('*', '.*?', $filtereds)) as $filtered) {
                if (!isset($this->applied_filters[$by][$filtered])) {
                    $this->applied_filters[$by][$filtered] = [];
                }
                if (is_string($filter)) {
                    foreach (explode('|', $filter) as $filter_name) {
                        $this->applied_filters[$by][$filtered][$type][] = $filter_name;
                    }
                }
                if (is_callable($filter)) {
                    $filter_name = '$filter-' . count($this->filters);
                    $this->add_filter($filter_name, $filter);
                    $this->applied_filters[$by][$filtered][$type][] = $filter_name;
                }
            }
        }

        /**
         * Calls the rouote filters.
         * @param string $route_name The name of route.
         * @param string $type       The type of filter.
         * @return bool
         */
        private function call_filter($route_name, $type) {
            if (isset($this->applied_filters['name'][$route_name][$type])) {
                foreach ($this->applied_filters['name'][$route_name][$type] as $filter_name) {
                    $params = \op::object(explode(',', 'op.route,op.request,op.response'));
                    if (!call_user_func_array($this->filters[$filter_name], $params)) {
                        return false;
                    }
                }
            }
            return true;
        }
    }

    /**
     * The Evenement class.
     * @package op
     */
    class Evenement {
        /**
         * The events. Key is event name, value is a array of handlers.
         * @var array
         */
        private $events = [];

        /**
         * The parameters of event handler. Key is event name, value is a array of parameters.
         * @var array
         */
        private $params = [];

        /**
         * @param string   $event   The name of event.
         * @param callable $handler The handler of event.
         * @param array    $params  The parameters of handler.
         */
        public function on($event, callable $handler, array $params) {
            if (!isset($this->events[$event])) {
                $this->events[$event] = [];
            }
            $this->events[$event][] = $handler;
            if (!isset($this->params[$event])) { 
                $this->params[$event] = []; 
            }
            $this->params[$event][] = $params;
        }

        /**
         * @param string   $event   The name of event.
         * @param callable $handler The handler of event.
         * @param array    $params  The parameters of handler.
         */
        public function once($event, callable $handler, array $params) {
            $once_handler = function () use (&$once_handler, $event, $handler) {
                $this->off($event, $once_handler);
                call_user_func_array($handler, func_get_args());
            };
            $this->on($event, $once_handler, $params);
        }

        /**
         * @param string|null   $event   The name of event.
         * @param callable|null $handler The handler of event.
         */
        public function off($event = null, $handler = null) {
            if (is_null($event)) {
                $this->events = [];
            } else {
                if (!isset($this->events[$event])) { 
                    return; 
                }
                if (!is_null($handler)) {
                    $index = array_search($handler, $this->events[$event], true);
                    if ($index !== false) {
                        unset($this->events[$event][$index]);
                        unset($this->params[$event][$index]);
                    }
                } else {
                    unset($this->events[$event]);
                }
            }
        }

        /**
         * Execute all handlers attached to the event.
         * @param string $event The event name.
         * @param array  $params Additional parameters to pass along to the event handler.
         */
        public function fire($event, array $params) {
            if (isset($this->events[$event])) {
                foreach ($this->events[$event] as $index => $handler) {
                    if (!empty($this->params[$event][$index])) {
                        $params = array_merge($params, $this->params[$event][$index]);
                    }
                    call_user_func_array($handler, [$params]);
                }
            }
        }
    }

    /**
     * The Request class represents the HTTP request.
     * @package op
     */
    class Request {
        /**
         * @var array HTTP headers.
         */
        private $headers = [];

        /**
         * @var mixed 请求体。
         */
        private $body;

        /**
         * @var string 请求URL。
         */
        private $url;

        /**
         * @var string 请求URI。
         */
        private $uri;

        /**
         * @var string 请求方法，即请求行中的请求方法。
         */
        private $method = '';

        /**
         * @var \op\Arrays 请求参数。
         */
        private $params;

        /**
         * Uploaded files.
         * @var array
         */
        private $files;

        public function __construct() {
            $properties = [
                'uri'      => $_SERVER['REQUEST_URI'],
                'url'      => explode('?', $_SERVER['REQUEST_URI'])[0],
                'method'   => $_SERVER['REQUEST_METHOD'] ?: 'GET',
                'referrer' => getenv('HTTP_REFERER') ?: '',
                'body'     => file_get_contents('php://input'),
                'params'   => \op::arrays(array_merge($_GET, $_POST)),
                'files'    => \op::arrays($_FILES),
            ];
            foreach ($properties as $name => $value) {
                $this->$name = $value;
            }
            if (isset($this->params['_method'])) {
                $this->method = strtoupper($this->params['_method']);
            }
        }

        /**
         * Gets the url of request.
         * @return string The url of request.
         */
        public function url($url = null) {
            if (is_null($url)) {
                return $this->url;
            }
            $this->url = $url;
        }

        /**
         * Gets the uri of request.
         * @return string The uri of request.
         */
        public function uri() {
            return $this->uri;
        }

        /**
         * Gets the HTTP method of request.
         * @return string The HTTP method of request.
         */
        public function method() {
            return $this->method;
        }

        /**
         * Check if it is a ajax request.
         * @return bool true or false.
         */
        public function is_ajax() {
            return getenv('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest';
        }

        /**
         * Gets the parameters of request.
         * @param string|null $key The key of parameter.
         * @return mixed The parameters of request.
         */
        public function param($key) {
            if (is_null($key) || !is_string($key)) {
                return $this->params;
            }
            return isset($this->params[$key]) ? $this->params[$key] : null;
        }

        public function upload($key) {
            if (is_null($key) || !is_string($key)) {
                return $this->files;
            }
            return isset($this->files[$key]) ? $this->files[$key] : null;
        }
    }

    /**
     * The Response class represents the HTTP response.
     * @package op
     */
    class Response {
        /**
         * @var int 响应状态码。
         */
        private $status = 200;

        /**
         * @var array HTTP响应头。
         */
        private $headers = [];

        /**
         * @var string 响应体。
         */
        private $body = '';

        /**
         * @var array HTTP响应状态码表。
         */
        public static $statuses = [
            200 => 'OK',
            302 => 'Found',
            404 => 'Not Found',
            500 => 'Internal Server Error'
        ];

        public function header($key, $value = null) {
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $this->headers[$k] = $v;
                }
            } else {
                $this->headers[$key] = $value;
            }
            return $this;
        }

        /**
         * @param int $status 状态码。
         * @return int|\op\Response 当前响应对象或者响应的状态码。
         */
        public function status($status = null) {
            if (is_null($status)) {
                return $this->status;
            }
            $this->status = $status;
            return $this;
        }

        /**
         * @param $output string 数据。
         * @return $this \op\Response 当前响应对象。
         */
        public function write($output) {
            $this->body .= $output;
            return $this;
        }

        /**
         * Sends the response and exits.
         */
        public function send() {
            if (!headers_sent()) {
                $status = static::$statuses[$this->status];
                if (strpos(php_sapi_name(), 'cgi') !== false) {
                    header(sprintf('Status: %d %s', $this->status,  $status), true);
                } else {
                    header(sprintf('%s %d %s', getenv('SERVER_PROTOCOL') ?: 'HTTP/1.1', $this->status, $status), true, $this->status);
                }
                foreach ($this->headers as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            header($key . ': ' . $v, false);
                        }
                    } else {
                        header($key . ': ' . $value);
                    }
                }
            }
            exit($this->body);
        }
    }

    /**
     * The Route class.
     * @package op
     */
    class Route {
        /**
         * @var string The pattern.
         */
        private $pattern;

        /**
         * @var string The URL.
         */
        private $url;

        /**
         * @var string The name.
         */
        private $name;

        /**
         * @var callable The handler.
         */
        private $handler;

        /**
         * @var array The parameters.
         */
        private $params = [];

        /**
         * @var array The conditions.
         */
        private $conditions = [];

        /**
         * @var array The HTTP methods.
         */
        private $methods = ['*'];

        /**
         * @var bool
         */
        private $ajax = false;

        public function __construct(array $args = []) {
            foreach ($args as $name => $value) {
                $this->$name = $value;
            }
        }

        /**
         * Matches the request.
         * @param Request $request HTTP请求。
         * @return bool|null 匹配状态。
         */
        public function matches(Request $request) {
            $is_matched = count(array_intersect([$request->method(), '*'], $this->methods)) > 0 && 
                ($this->ajax === $request->is_ajax());
            $url = $request->url();
            if ($this->pattern === '*' || $this->pattern === $url) {
                return $is_matched && true;
            }
            $keys  = [];
            $regex = preg_replace_callback('#\{(\w+)\}#', function ($matches) use (&$keys) {
                    $keys[] = $matches[1];
                        return '(?<' . $matches[1] . '>' . 
                            (isset($this->conditions[$matches[1]]) ? $this->conditions[$matches[1]] : '[^/\?]+') 
                            . ')';
                }, $this->pattern
            );
            if (preg_match('#^' . $regex . '/?$#i', $url, $matches)) {
                foreach ($keys as $key) {
                    if (array_key_exists($key, $matches)) {
                        $this->params[$key] = $matches[$key];
                    }
                }
                return $is_matched && true;
            }
        }

        /**
         * Gets the parameters of route.
         * @return array The parameters of route.
         */
        public function params() {
            return $this->params;
        }

        /**
         * Gets the handler of route.
         * @return callable The handler of route.
         */
        public function handler() {
            return $this->handler;
        }

        /**
         * Gets the name of route.
         * @return string The name of route.
         */
        public function name() {
            return $this->name;
        }

        /**
         * Access the url of route.
         * @param string|null $url The url of route.
         * @return string|null The url of route or null.
         */
        public function url($url = null) {
            if (is_null($url)) {
                return $this->url;
            }
            $this->url = $url;
        }

        /**
         * Gets the pattern of route.
         * @return string The pattern of route.
         */
        public function pattern() {
            return $this->pattern;
        }

        /**
         * Access the HTTP request methods of route.
         * @param null $methods The HTTP methods.
         * @return Route|array The HTTP methods or self.
         */
        public function method($methods = null) {
            if (is_null($methods)) { 
                return $this->methods; 
            }
            $this->methods = explode('|', $methods);
            return $this;
        }

        /**
         * Sets the conditions of route.
         * @param array $conditions The conditions of route.
         * @return Route Self.
         */
        public function match(array $conditions) {
            $this->conditions = $conditions;
            return $this;
        }

        /**
         * @param string|callable $filter 过滤器。
         * @return Route $this 当前路由对象。
         */
        public function before($filter) {
            \op::before($this->name, $filter);
            return $this;
        }

        /**
         * @param string|callable $filter 过滤器。
         * @return Route $this 当前路由对象。
         */
        public function after($filter) {
            \op::after($this->name, $filter);
            return $this;
        }

        /**
         * Access the flag of ajax.
         * @param bool|null $ajax The flag of ajax.
         * @return Route|bool Self or the ajax flag.
         */
        public function ajax($ajax = null) {
            if (is_null($ajax)) { 
                return $this->ajax; 
            }
            $this->ajax = (bool) $ajax;
            return $this;
        }
    }

    /**
     * The Router class.
     * @package op
     */
    class Router {
        /**
         * @var array The routes. Key is name of route, value is route object.
         */
        private $routes = [];

        /**
         * @var array The group of route.
         */
        private $group = [];

        /**
         * Adds a new route.
         * @param string   $pattern The pattern of route.
         * @param callable $handler The handler of route.
         * @return Route Self.
         */
        public function add_route($pattern, callable $handler) {
            $name = '$route-' . count($this->routes); // Generates default filter's name.
            if (strpos($pattern, ' as ') !== false) {
                list($pattern, $name) = explode(' as ', trim($pattern), 2);
            }
            if (isset($this->group['prefix'])) {
                $pattern = ($pattern === '/') ? '' : $pattern;
                $pattern = $this->group['prefix'] . $pattern; 
            }
            if (isset($this->group['before'])) { 
                \op::before($name, $this->group['before']); 
            }
            if (isset($this->group['after']))  { 
                \op::after($name, $this->group['after']); 
            }
            return $this->routes[$name] = new Route([
                'pattern' => trim($pattern), 
                'handler' => $handler, 
                'name' => $name
            ]);
        }

        /**
         * Routes the current request.
         * @param Request $request The current request.
         * @return null|Route Self or null.
         */
        public function route(Request $request) {
            foreach ($this->routes as $route) {
                if ($route->matches($request)) {
                    $route->url($request->url());
                    $args = [
                        'pattern' => $route->pattern(),
                        'methods' => $route->method(),
                        'name'    => $route->name(),
                        'url'     => $request->url()
                    ];
                    $current_route = new Route($args); // Create current route.
                    \op::object('op.route', $current_route);
                    return $route;
                }
            }
        }

        /**
         * Access the current group of router.
         * @param array $group The group.
         * @return array|null The group or null.
         */
        public function group(array $group = null) {
            if (is_null($group)) { 
                return $this->group; 
            }
            $this->group = $group;
        }
    }

    /**
     * The Crude class.
     * @package op
     */
    class Crude {
        /**
         * The database connection.
         * @var \PDO
         */
        private $connection;

        /**
         * The Data Source Name.
         * @var string
         */
        private $dsn;

        /**
         * The username for the DSN string.
         * @var string
         */
        private $username = null;

        /**
         * The password for the DSN string.
         * @var string
         */
        private $password = null;

        /**
         * A (key => value) array of driver-specific connection options.
         * @var array
         */
        private $options = [];

        public function __construct(array $configs) {
            foreach ($configs as $key => $value) {
                $this->$key = $value;
            }

            try {
                $this->connection = new \PDO($this->dsn, $this->username, $this->password);
                foreach ($this->options as $attr => $value) {
                    $this->connection->setAttribute($attr, $value);
                }
                $this->connection->exec('SET NAMES \'utf8\'');
            } catch (\PDOException $e) {
                throw new \PDOException('Can not create the connection! ' . $e->getMessage());
            }
        }

        /**
         * 
         * @param  string   $query   The SQL statement.
         * @param  callable $handler The handler of result set.
         * @param  array    $params  The parameters.
         * @return mixed             The result.
         */
        public function query($query, callable $handler, array $params = []) {
            if ($result_set = $this->execute($query, $params)) {
                return call_user_func_array($handler, [$result_set]);
            }
        }

        public function columns($query, array $params = []) {
            return $this->query($query, function ($result_set) {
                $columns = [];
                while ($row = $result_set->fetchColumn()) {
                    $columns[] = $row;
                }
                return $columns;
            }, $params);
        }

        public function column($query, array $params = []) {
            $columns = $this->columns($query, $params);
            return array_shift($columns);
        }

        public function named_columns($query, array $params = []) {
            return $this->query($query, function ($result_set) {
                $named_columns = [];
                while ($row = $result_set->fetch(\PDO::FETCH_NUM)) {
                    for ($i = 0; $i < $result_set->columnCount(); $i++) {
                        $column = $result_set->getColumnMeta($i)['name'];
                        $named_columns[$column][] = $row[$i];
                    }
                }
                return $named_columns;
            }, $params);
        }

        public function rows($query, array $params = []) {
            return $this->query($query, function ($result_set) {
                $rows = [];
                while ($row = $result_set->fetch(\PDO::FETCH_NUM)) {
                    $rows[] = $row;
                }
                return $rows;
            }, $params);
        }

        public function maps($query, array $params = []) {
            return $this->query($query, function ($result_set) {
                $maps = [];
                while ($row = $result_set->fetch(\PDO::FETCH_ASSOC)) {
                    $maps[] = $row;
                }
                return $maps;
            }, $params);
        }

        public function pairs($query, array $params = []) {
            return $this->query($query, function ($result_set) {
                $pairs = [];
                while ($row = $result_set->fetch(\PDO::FETCH_NUM)) {
                    $pairs[$row[0]] = $row[1];
                }
                return $pairs;
            }, $params);
        }

        private function keyeds($query, callable $create_value, array $params) {
            return $this->query($query, function ($result_set) use ($create_value) {
                $keyeds = [];
                if ($result_set->columnCount() < 0) {
                    return $keyeds;
                }
                $column = $result_set->getColumnMeta(0)['name'];
                while ($row = $result_set->fetch(\PDO::FETCH_ASSOC)) {
                    $keyeds[$row[$column]] = call_user_func_array($create_value, [$row]);
                }
                return $keyeds;
            }, $params);
        }

        private function keys_multis($query, callable $create_value, array $params) {
            return $this->query($query, function ($result_set) use ($create_value) {
                $column = $result_set->getColumnMeta(0)['name'];
                $keyeds = [];
                while ($row = $result_set->fetch(\PDO::FETCH_ASSOC)) {
                    if (!isset($row[$column])) { 
                        return null; 
                    }
                    if (!isset($keyeds[$row[$column]])) {
                        $keyeds[$row[$column]] = [];
                    }
                    $keyeds[$row[$column]][] = call_user_func_array($create_value, [$row]);
                }
                return $keyeds;
            }, $params);
        }

        public function keys_maps($query, array $params = []) {
            return $this->keys_multis($query, function ($row) {
                return $row;
            }, $params);            
        }

        public function keys_rows($query, array $params = []) {
            return $this->keys_multis($query, function ($row) {
                return array_values($row);
            }, $params);            
        }        

        public function key_maps($query, array $params = []) {
            return $this->keyeds($query, function ($row) {
                return $row;
            }, $params);
        }

        public function key_rows($query, array $params = []) {
            return $this->keyeds($query, function ($row) {
                return array_values($row);
            }, $params);
        }

        private function keyed_multis($query, callable $do_multi, array $params) {
            return $this->keyeds($query, function ($row) use ($do_multi) {
                $multi = [];
                foreach ($row as $key => $value) {
                    list($table, $key) = explode('_', $key, 2);
                    if (!isset($multi[$table])) {
                        $multi[$table] = [];
                    }
                    call_user_func_array($do_multi, [$table, $key, $value, &$multi]);
                }
                return $multi;
            }, $params);
        }

        public function keyed_multimaps($query, array $params = []) {
            return $this->keyed_multis($query, function ($table, $key, $value, &$multi) {
                $multi[$table][$key] = $value;
            }, $params);
        }

        public function keyed_multirows($query, array $params = []) {
            return $this->keyed_multis($query, function ($table, $key, $value, &$multi) {
                $multi[$table][] = $value;
            }, $params);
        }

        private function listed_multis($query, callable $do_multi, array $params) {
            return $this->query($query, function ($result_set) use ($do_multi) {
                $listeds = [];
                while ($row = $result_set->fetch(\PDO::FETCH_ASSOC)) {
                    $multi = [];
                    foreach ($row as $key => $value) {
                        list($table, $key) = explode('_', $key, 2);
                        if (!isset($multi[$table])) {
                            $multi[$table] = [];
                        }
                        call_user_func_array($do_multi, [$table, $key, $value, &$multi]);
                    }
                    $listeds[] = $multi;
                }
                return $listeds;
            }, $params);
        }

        public function multimaps($query, array $params = []) {
            return $this->listed_multis($query, function ($table, $key, $value, &$multi) {
                $multi[$table][$key] = $value;
            }, $params);
        }

        public function multirows($query, array $params = []) {
            return $this->listed_multis($query, function ($table, $key, $value, &$multi) {
                $multi[$table][] = $value;
            }, $params);
        }

        public function multirow($query, array $params = []) {
            $multirows = $this->multirows($query, $params);
            return array_shift($multirows);
        }

        public function row($query, array $params = []) {
            $rows = $this->rows($query, $params);
            return array_shift($rows);
        }

        public function map($query, array $params = []) {
            $maps = $this->maps($query, $params);
            return array_shift($maps);
        }

        public function multimap($query, array $params = []) {
            $multimaps = $this->multimaps($query, $params);
            return array_shift($multimaps);
        }

        public function update($query, array $params = []) {
            if ($statement = $this->execute($query, $params)) {
                return $statement->rowCount();
            }
        }

        public function batch($query, array $params = []) {
            $affected_counts = [];
            try {
                $statement = $this->connection->prepare($query);
                foreach ($params as $param) {
                    if (!is_array($param)) { continue; }
                    if ($statement->execute($param)) {
                        $affected_counts[] = $statement->rowCount();
                    }
                }
            } catch (\PDOException $e) {
                // TODO log
                throw $e;
            }
            return $affected_counts;
        }

        private function execute($query, array $params = []) {
            try {
                $statement = $this->connection->prepare($query);
                $statement->execute($params);
            } catch (\PDOException $e) {
                // TODO log
                throw $e;
            }
            return $statement;
        }

        public function create($table, array $datas) {
            $ids = [];
            if (!isset($datas[0])) {
                $datas = [$datas];
            }
            foreach ($datas as $data) {
                $fields = implode(',', array_keys($data));
                $values = rtrim(str_repeat('?,', count($data)), ',');
                $query = 'INSERT INTO ' . $table . '(' . $fields . ') ' . 'VALUES(' . $values . ')';
                if ($this->execute($query, array_values($data))) {
                    $ids[] = $this->connection->lastInsertId();
                }
            }
            return count($ids) > 1 ? $ids : $ids[0];
        }

        public function remove($table, $id, $column = 'id') {
            $query = 'DELETE FROM ' . $table . ' WHERE ' . $column . '=?';
            if ($statement = $this->execute($query, [$id])) {
                return $statement->rowCount();
            }
        }

        public function modify($table, array $columns, $id, $column = 'id') {
            $updates = implode('=?,', array_keys($columns)) . '=?';
            $query = 'UPDATE '. $table . ' SET ' . $updates .  ' WHERE ' . $column . '=?';
            if ($statement = $this->execute($query, array_values($columns + [$id]))) {
                return $statement->rowCount();
            }
        }

        public function select($table, $id, $columns = []) {
            $selections = !empty($columns) ? implode(', ', $columns) : '*';
            $query = 'SELECT ' . $selections . ' FROM ' . $table . ' WHERE id=?';
            if ($result_set = $this->execute($query, [$id])) {
                return $result_set->fetch(\PDO::FETCH_ASSOC);
            }
        }

        public function transaction(callable $transactional) {
            if ($this->connection->inTransaction()) {
                return; // report a exception or error. do not support recursive transactions.
            }
            $this->connection->beginTransaction();
            try {
                call_user_func_array($transactional, [$this]);
                return $this->connection->commit();
            } catch (\PDOException $e) {
                $this->connection->rollBack();
                return false;
            }
        }
    }
}
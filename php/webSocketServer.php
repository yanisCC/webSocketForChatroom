<?php

class WebSocketServer
{
    private $sockets;   // 所有socket连接池包括服务端socket
    private $users;     // 所有连接用户
    private $server;    // 服务端 socket

    public function __construct($ip, $port)
    {
        $this->server = socket_create(AF_INET, SOCK_STREAM, 0);
        $this->sockets = array($this->server);
        $this->users = array();
        socket_bind($this->server, $ip, $port);
        socket_listen($this->server, 500);
        echo "[*]正在监听:" . $ip . ":" . $port . "\n";
    }

    public function run()
    {
        $active_sockets = NULL; // 将观察数组中 列出的套接字，以查看字符是否可供读取
        $write = NULL;          // 将监视数组中 列出的套接字以查看写入是否不会阻塞。
        $except = NULL;         // 将监视阵列中 列出的套接字是否有异常。
        while (true) {
            $active_sockets = $this->sockets;
            socket_select($active_sockets, $write, $except, NULL);
            //这个函数很重要
            //前三个参数时传入的是数组的引用,会依次从传入的数组中选择出可读的,可写的,异常的socket,我们只需要选择出可读的socket
            //最后一个参数tv_sec很重要
            //第一，若将NULL以形参传入，即不传入时间结构，就是将select置于阻塞状态，一定等到监视文件描述符集合(socket数组)中某个文件描
            //述符发生变化为止；
            //第二，若将时间值设为0秒0毫秒，就变成一个纯粹的非阻塞函数，不管文件描述符是否有变化，都立刻返回继续执行，文件无
            //变化返回0，有变化返回一个正值；
            //第三，timeout的值大于0，这就是等待的超时时间，即 select在timeout时间内阻塞，超时时间之内有事件到来就返回了，
            //否则在超时后不管怎样一定返回，返回值同上述。
            foreach ($active_sockets as $socket) {
                if ($socket == $this->server) {
                    //服务端 socket可读说明有新用户连接
                    $user = socket_accept($this->server);
                    $addr = NULL;
                    $por = NULL;
                    socket_getpeername($user, $addr, $por);
                    $key = uniqid();
                    $this->sockets[] = $user;
                    $this->users[$key] = array(
                        'socket' => $user,
                        'handshake' => false, //是否完成websocket握手
                        'address' => $addr,
                        'port' => $por
                    );
                } else {
                    //用户socket可读
                    $buffer = '';
                    $bytes = socket_recv($socket, $buffer, 1024, 0);
                    $key = $this->find_user_by_socket($socket); //通过socket在users数组中找出user
                    if ($bytes <= 6) {
                        //没有数据 关闭连接
                        $this->disconnect($socket);
                    } else {
                        //没有握手就先握手
                        if (!$this->users[$key]['handshake']) {
                            $this->handshake($key, $buffer);
                            echo $this->users[$key]['address']."进入房间，当前房间人数为：".count($this->users)."\n";
                        } else {
                            //握手后
                            //解析消息 websocket协议有自己的消息格式
                            //解码 编码过程固定的
                            $msg = array(
                                'from' => $this->users[$key]['address'],
                                'msg' => $this->msg_decode($buffer),
                                'num' => count($this->users)
                            );
                            echo json_encode($msg)."\r\n";
                            //编码后发送回去
                            $this->push_msg_for_all($msg, $socket);
                        }
                    }
                }
            }
        }
    }

    //全局广播消息 消息为数组格式
    private function push_msg_for_all($msg,$c_socket=null){
        $msg=$this->msg_encode(json_encode($msg));
        //如果$c_socket不为null，除了服务器和自己都发送
        //如果$c_socket为null，除了服务器都发送
        foreach ($this->sockets as $socket){
            if ($socket != $this->server and $socket != $c_socket){
                socket_write($socket,$msg,strlen($msg));
            }
        }
    }

    //解除连接
    private function disconnect($socket)
    {
        $key=$this->find_user_by_socket($socket);
        $address = $this->users[$key]['address'];
        unset($this->users[$key]);
        foreach ($this->sockets as $k=>$v){
            if ($v==$socket)
                unset($this->sockets[$k]);
        }
        socket_shutdown($socket);
        socket_close($socket);
        echo $address."断开了连接，当前房间人数为：".count($this->users)."\n";
        $this->push_msg_for_all($address."离开了房间", $socket);
    }

    //通过socket在users数组中找出user
    private function find_user_by_socket($socket)
    {
        foreach ($this->users as $key => $user) {
            if ($user['socket'] == $socket) {
                return $key;
            }
        }
        return -1;
    }

    private function handshake($k, $buffer)
    {
        //截取Sec-WebSocket-Key的值并加密
        $buf = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);
        $key = trim(substr($buf, 0, strpos($buf, "\r\n")));
        $new_key = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

        //按照协议组合信息进行返回
        $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        socket_write($this->users[$k]['socket'], $new_message, strlen($new_message));

        //对已经握手的client做标志
        $this->users[$k]['handshake'] = true;
        return true;
    }


    //编码 把消息打包成websocket协议支持的格式
    private function msg_encode($buffer)
    {
        $len = strlen($buffer);
        if ($len <= 125) {
            return "\x81" . chr($len) . $buffer;
        } else if ($len <= 65535) {
            return "\x81" . chr(126) . pack("n", $len) . $buffer;
        } else {
            return "\x81" . chr(127) . pack("xxxxN", $len) . $buffer;
        }
    }

    //解码 解析websocket数据帧
    private function msg_decode($buffer)
    {
        $len = null;
        $masks = null;
        $data = null;
        $decoded = null;
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }
}

$ws = new WebSocketServer('0.0.0.0', 39390);
$ws->run();

// socket_create() 学习网站：https://www.php.net/manual/zh/function.socket-create.php
// socket_bind() 学习网站：https://www.php.net/manual/zh/function.socket-bind.php
// socket_listen() 学习网站：https://www.php.net/manual/zh/function.socket-listen.php
// socket_select() 学习网站：https://www.php.net/manual/zh/function.socket-select.php
// socket_accept() 学习网站：https://www.php.net/manual/zh/function.socket-accept.php
// socket_recv() 学习网站：https://www.php.net/manual/zh/function.socket-recv.php
// socket_write() 学习网站：https://www.php.net/manual/zh/function.socket-write.php
// socket_shutdown() 学习网站：https://www.php.net/manual/zh/function.socket-shutdown.php
// socket_close() 学习网站：https://www.php.net/manual/zh/function.socket-close.php
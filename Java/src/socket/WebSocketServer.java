package socket;

import java.net.*;
import java.util.*;

import org.java_websocket.*;
import org.java_websocket.handshake.ClientHandshake;


public class WebSocketServer extends org.java_websocket.server.WebSocketServer
{
    private String ip;
    private int port;
    public ArrayList<WebSocket> sockets;
    private  Map<String, User> users;

    public WebSocketServer(){
        this("0.0.0.0", 39390);
    }

    public WebSocketServer(String ip, int port){
        super(new InetSocketAddress(ip, port), 500);
        this.ip = ip;
        this.port = port;
    }

    @Override
    public void onOpen(WebSocket socket, ClientHandshake handshake) {
        String address = socket.getRemoteSocketAddress().getAddress().getHostAddress();
        int ip = socket.getRemoteSocketAddress().getPort();
        String key = UUID.randomUUID().toString();
        User user = new User(socket, address, ip);
        users.put(key, user);
        sockets.add(socket);
        System.out.println(user.address + "进入房间，当前房间人数为：" + users.size());
    }

    @Override
    public void onClose(WebSocket socket, int code, String reason, boolean remote) {
        User user = find_user_by_socket(socket);
        if (user != null) {
            System.out.println(user.address + "断开了连接，当前房间人数为：" + users.size());
            this.push_msg_for_all(new Msg(user.address, user.address + "离开了房间", users.size()), user);
            sockets.removeIf(webSocket -> webSocket == socket);
            users.remove(find_user_key_by_socket(socket));
        }
    }

    @Override
    public void onMessage(WebSocket socket, String message) {
        User user = find_user_by_socket(socket);
        assert user != null;
        Msg msg = new Msg(user.address, message, users.size());
        System.out.println(msg.BuildMsg());
        this.push_msg_for_all(msg, user);
    }

    @Override
    public void onError(WebSocket socket, Exception ex) {

    }

    @Override
    public void onStart() {
        this.users = new HashMap<>();
        this.sockets = new ArrayList<>();
        System.out.println("[*]正在监听:" + ip + ":" + port);
    }

    private User find_user_by_socket(WebSocket socket)
    {
        for (Map.Entry<String, User> entry : users.entrySet()) {
            if (entry.getValue().socket == socket)
            {
                return entry.getValue();
            }
        }
        return null;
    }

    private String find_user_key_by_socket(WebSocket socket)
    {
        for (Map.Entry<String, User> entry : users.entrySet()) {
            if (entry.getValue().socket == socket)
            {
                return entry.getKey();
            }
        }
        return null;
    }

    private void push_msg_for_all(Msg msg, User u)
    {
        for (Map.Entry<String, User> entry : users.entrySet()) {
            if (entry.getValue() != u)
            {
                entry.getValue().socket.send(msg.BuildMsg());
            }
        }
    }

    public static void main(String[] args) {
        new WebSocketServer().start();
    }
}

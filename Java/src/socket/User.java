package socket;

import org.java_websocket.WebSocket;

import java.net.Socket;

public class User {
    public WebSocket socket;
    public boolean handShake;
    public String address;
    public int port;

    public User(WebSocket socket, String address, int port, boolean handShake)
    {
        this.socket = socket;
        this.handShake = handShake;
        this.address = address;
        this.port = port;
    }

    public User(WebSocket socket, String address, int port)
    {
        this(socket, address, port, false);
    }
}

package socket;

import com.alibaba.fastjson.JSONObject;

public class Msg {
    private String from;
    private String msg;
    private int num;

    public Msg(String from, String msg, int num)
    {
        this.from = from;
        this.msg = msg;
        this.num = num;
    }

    public String BuildMsg()
    {
        JSONObject obj = new JSONObject();
        obj.put("from", this.from);
        obj.put("msg", this.msg);
        obj.put("num", this.num);
        return obj.toJSONString();
    }

    public String getMsg()
    {
        return msg;
    }
}

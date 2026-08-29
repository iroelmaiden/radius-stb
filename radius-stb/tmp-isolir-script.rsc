:local uName [/ppp active get [find] name]
:local uIP [/ppp active get [find where name=$uName] address]
:local rMsg [/interface pppoe-server get [find where running] reply-message]
:if ([:find $rMsg "ISOLIR"] != 0) do={
    /ip firewall address-list add list=pppoe-isolir address=$uIP comment=$uName timeout=0s
    /log warning ("ISOLIR: " . $uName . " (" . $uIP . ") blocked")
}

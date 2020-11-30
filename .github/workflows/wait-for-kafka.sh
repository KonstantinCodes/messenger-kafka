#! /bin/bash
end=$((SECONDS+60))

while [ $SECONDS -lt $end ]; do
    if echo dump | nc localhost 2181 | grep broker ; then
        exit 0
    else
        echo "Kafka didn't start in time"
    fi
    sleep 1
done
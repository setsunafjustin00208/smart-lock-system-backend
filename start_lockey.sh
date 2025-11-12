#!/bin/bash

SESSION="lockey"
WORKDIR=/home/lockey/Documents/lockey/backend

# Kill existing session if any
/usr/bin/tmux kill-session -t $SESSION 2>/dev/null

# Start a new session detached
/usr/bin/tmux new-session -d -s $SESSION -c $WORKDIR

# Pane 1: PHP Built-in Server (listen on all interfaces)
/usr/bin/tmux send-keys -t $SESSION "while true; do php -S 0.0.0.0:8080 -t public/; echo 'Server crashed. Restarting in 3s...'; sleep 3; done" C-m

# Split horizontally → Pane 2
/usr/bin/tmux split-window -h -t $SESSION -c $WORKDIR
/usr/bin/tmux send-keys -t $SESSION "while true; do php spark websocket:start --port 3000; echo 'WebSocket crashed. Restarting in 3s...'; sleep 3; done" C-m

# Split vertically from pane 0 → Pane 3
/usr/bin/tmux select-pane -t 0
/usr/bin/tmux split-window -v -t $SESSION -c $WORKDIR
/usr/bin/tmux send-keys -t $SESSION "while true; do ngrok http 8080 --url=lockey.ngrok.io; echo 'ngrok stopped. Restarting in 3s...'; sleep 3; done" C-m

# Split vertically from pane 1 → Pane 4
/usr/bin/tmux select-pane -t 1
/usr/bin/tmux split-window -v -t $SESSION -c $WORKDIR
/usr/bin/tmux send-keys -t $SESSION "while true; do php spark hardware:monitor; echo 'Hardware monitor stopped. Restarting in 3s...'; sleep 3; done" C-m

# Arrange in 2x2 grid
/usr/bin/tmux select-layout -t $SESSION tiled

exit 0

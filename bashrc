
# curl https://phuslu.github.io/bashrc | tee -a ~/.bashrc
if test "${HOME%/*}" = "/Users" ; then
   #curl -LOJ https://phuslu.github.io/candidate/IR_Black.terminal
   alias ls='ls -G'
else
   alias ls='ls -ph --color=auto'
fi
alias ll='ls -alF'
alias rm='rm -i'
alias mv='mv -i'
alias cp='cp -i'
export HISTTIMEFORMAT="%Y-%m-%d %T "
export HISTCONTROL=ignoreboth
export HISTSIZE=100000
export HISTFILESIZE=2000000
export PS1='\[\e[01;32m\]\u@\h\[\e[00;33m\] \w \[\e[1;$((31+3*!$?))m\]\$\[\e[00m\] '
#export PROMPT_COMMAND="history -a; history -c; history -r; $PROMPT_COMMAND"

if test -n "$BASH_VERSION"; then
    alias ssh='LC_ALL=en_US.UTF-8 ssh'
    test grep --version >/dev/null 2>&1 && alias grep='grep --color'
    eval $(SHELL=/bin/bash $(type -p dircolors))
    shopt -s checkwinsize
    shopt -s histappend
    if [[ "xterm-256color xterm screen rxvt cygwin" == *"$TERM"* ]] ; then
        bind '"\e[B": history-search-forward'
        bind '"\e[A": history-search-backward'
        set bell-style none
        set show-all-if-ambiguous on
        set completion-ignore-case on
        if test "${HOME%/*}" = "/Users" ; then
            #brew install bash-completion
            if [ -f /usr/local/etc/bash_completion ]; then
                source /usr/local/etc/bash_completion
            fi
        else
            #sudo apt install -y bash-completion
            if [ -f /usr/share/bash-completion/bash_completion ]; then
                source /usr/share/bash-completion/bash_completion
            fi
        fi
        if [ -f ~/.git-completion.bash ]; then
            #curl -Lf https://raw.githubusercontent.com/git/git/master/contrib/completion/git-completion.bash >~/.git-completion.bash
            source ~/.git-completion.bash
        fi
        if [ -f ~/.git-prompt.sh ]; then
            #curl -Lf https://raw.githubusercontent.com/git/git/master/contrib/completion/git-prompt.sh >~/.git-prompt.sh
            source ~/.git-prompt.sh
            export PS1='\[\e]0;\h:\w\a\]\n\[\e[01;32m\]\u@\h\[\e[00;33m\] \w$(__git_ps1 " (%s)")\n\[\e[1;$((31+3*!$?))m\]\$\[\e[00m\] '
        else
            export PS1='\[\e]0;\h:\w\a\]\n\[\e[01;32m\]\u@\h\[\e[00;33m\] \w\n\[\e[1;$((31+3*!$?))m\]\$\[\e[00m\] '
        fi
        if [ -f ~/.z.sh ]; then
            #curl -Lf https://raw.githubusercontent.com/rupa/z/master/z.sh >~/.z.sh
            source ~/.z.sh
        fi
    fi
fi

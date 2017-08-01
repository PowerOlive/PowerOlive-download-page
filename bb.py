#!/usr/bin/env python3
# coding:utf-8

import sys

PY3 = sys.version >= '3'
if not PY3:
    reload(sys).setdefaultencoding('utf-8')

import base64
import email.utils
import getopt
import hashlib
import json
import logging
import os
import re
import socket
import struct
import sys
import telnetlib
import time

if PY3:
    from urllib.request import urlopen, Request
    from itertools import zip_longest
else:
    from urllib2 import urlopen, Request
    from itertools import izip_longest as zip_longest

logging.basicConfig(format='%(levelname)s:%(message)s', level=logging.INFO)

def getip(iface=''):
    if not iface:
        sock = socket.socket()
        sock = socket.socket(type=socket.SOCK_DGRAM)
        sock.connect(('8.8.8.8', 53))
        ip = sock.getsockname()[0]
        sock.close()
        return ip
    lines = os.popen('ip -o addr show {}'.format(iface)).read().splitlines()
    for line in lines:
        _, name, network, addr = line.strip().split()[:4]
        if network in (('inet', 'inet6')):
            return addr.split('/')[0]


def getip_from_akamai():
    ip = urlopen('http://whatismyip.akamai.com/', timeout=5).read()
    return ip


def getip_from_3322():
    ip = urlopen('http://ip.3322.net/', timeout=5).read()
    return ip


def f3322_ddns(username, password, hostname, ip):
    api_url = 'http://members.3322.net/dyndns/update?hostname=%s&myip=%s&wildcard=OFF&offline=NO' % (hostname, ip)
    data = username + ':' + password
    headers = {'Authorization': 'Basic %s' % base64.b64encode(data.encode()).decode()}
    resp = urlopen(Request(api_url, data=None, headers=headers), timeout=5)
    logging.info('f3322_ddns hostname=%r to ip=%r result: %s', hostname, ip, resp.read())


def cx_ddns(api_key, api_secret, domain, ip=''):
    lip = socket.gethostbyname(domain)
    rip = getip_from_akamai()
    if lip == rip:
        logging.info('remote ip and local ip is same to %s, exit.', lip)
        return
    api_url = 'https://www.cloudxns.net/api2/ddns'
    data = json.dumps({'domain': domain, 'ip': ip, 'line_id': '1'})
    date = email.utils.formatdate()
    api_hmac = hashlib.md5(''.join((api_key, api_url, data, date, api_secret)).encode()).hexdigest()
    headers = {'API-KEY': api_key, 'API-REQUEST-DATE': date, 'API-HMAC': api_hmac, 'API-FORMAT': 'json'}
    resp = urlopen(Request(api_url, data=data.encode(), headers=headers), timeout=5)
    logging.info('cx_ddns domain=%r to ip=%r result: %s', domain, ip, resp.read())


def cx_update(api_key, api_secret, domain_id, host, ip):
    api_url = 'https://www.cloudxns.net/api2/record/{}'.format(domain_id)
    date = email.utils.formatdate()
    api_hmac = hashlib.md5(''.join((api_key, api_url, date, api_secret)).encode()).hexdigest()
    headers = {'API-KEY': api_key, 'API-REQUEST-DATE': date, 'API-HMAC': api_hmac, 'API-FORMAT': 'json'}
    resp = urlopen(Request(api_url, data=None, headers=headers), timeout=5)
    data = json.loads(resp.read().decode())['data']
    record_id = int(next(x['record_id'] for x in data if x['type']==('AAAA' if ':' in ip else 'A') and x['host']==host))
    logging.info('cx_update query domain_id=%r host=%r to record_id: %r', domain_id, host, record_id)
    api_url = 'https://www.cloudxns.net/api2/record/{}'.format(record_id)
    data = json.dumps({'domain_id': domain_id, 'host': host, 'value': ip})
    date = email.utils.formatdate()
    api_hmac = hashlib.md5(''.join((api_key, api_url, data, date, api_secret)).encode()).hexdigest()
    headers = {'API-KEY': api_key, 'API-REQUEST-DATE': date, 'API-HMAC': api_hmac, 'API-FORMAT': 'json'}
    request = Request(api_url, data=data.encode(), headers=headers)
    request.get_method = lambda: 'PUT'
    resp = urlopen(request, timeout=5)
    logging.info('cx_update update domain_id=%r host=%r ip=%r result: %r', domain_id, host, ip, resp.read())
    return


def wol(mac='18:66:DA:17:A2:95', broadcast='192.168.1.255'):
    if len(mac) == 12:
        pass
    elif len(mac) == 12 + 5:
        mac = mac.replace(mac[2], '')
    else:
        raise ValueError('Incorrect MAC address format')
    data = ''.join(['FFFFFFFFFFFF', mac * 20])
    send_data = b''
    # Split up the hex values and pack.
    for i in range(0, len(data), 2):
        send_data = b''.join([send_data, struct.pack('B', int(data[i: i + 2], 16))])
    # Broadcast it to the LAN.
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
    sock.sendto(send_data, (broadcast, 7))
    logging.info('wol packet sent to MAC=%r', mac)


def capture(url, wait_for_text='', selector='body', viewport_size='800x450', filename='capture.png'):
    """see https://hub.docker.com/r/phuslu/ghost.py/"""
    import ghost
    logging.info('create ghost.py Session')
    session = ghost.Session(ghost.Ghost(), viewport_size=tuple(map(int, viewport_size.split('x'))))
    logging.info('open %r', url)
    session.open(url)
    if wait_for_text:
        logging.info('wait_for_text %r', wait_for_text)
        session.wait_for_text(wait_for_text)
    else:
        logging.info('wait_for_page_loaded')
        session.wait_for_page_loaded()
    if '/' not in filename:
        filename = '/data/' + filename
    logging.info('capture selector=%r to %r', selector, filename)
    session.capture_to(filename, selector=selector)
    os.chmod(filename, 0o666)
    htmlfile = os.path.splitext(filename)[0] + '.html'
    open(htmlfile, 'wb').write(session.content.encode('utf-8'))
    os.chmod(htmlfile, 0o666)


def tcptop(pid=None, interval='1'):
    if not os.environ.get('WATCHED'):
        os.environ['WATCHED'] = '1'
        os.execv('/usr/bin/watch', ['watch', '-n' + interval, ' '.join(sys.argv)])
    lines = os.popen('ss -ntpi').read().splitlines()
    lines.pop(0)
    info = {}
    for i in range(0, len(lines), 2):
        line, next_line = lines[i], lines[i+1]
        state, _, _, laddr, raddr = line.split()[:5]
        apid = '-'
        comm = '-'
        if 'users:' in line:
            m = re.search(r'"(.+?)".+pid=(\d+)', line)
            comm, apid = m.group(1, 2)
        metrics = dict((k,int(v) if re.match(r'^\d+$', v) else v) for k, v in re.findall(r'([a-z_]+):(\S+)', next_line))
        bytes_acked = metrics.get('bytes_acked', 0)
        bytes_received = metrics.get('bytes_received', 0)
        if pid and apid != pid:
            continue
        if laddr.startswith(('127.', 'fe80::', '::1')) or raddr.startswith(('127.', 'fe80::', '::1')):
            continue
        if bytes_acked == 0 or bytes_received == 0:
            continue
        if not state.startswith('ESTAB'):
            continue
        laddr = laddr.lstrip('::ffff:')
        raddr = raddr.lstrip('::ffff:')
        if bytes_acked and bytes_received and state.startswith('ESTAB'):
            info[laddr, raddr] = (apid, comm, bytes_acked, bytes_received)
    print("%-6s %-12s %-21s %-21s %6s %6s" % ("PID", "COMM", "LADDR", "RADDR", "RX_KB", "TX_KB"))
    infolist = sorted(info.items(), key=lambda x:(-x[1][-2], -x[1][-1]))
    for (laddr, raddr), (pid, comm, bytes_acked, bytes_received) in infolist:
        rx_kb  = bytes_received//1024 
        tx_kb  = bytes_acked//1024
        if rx_kb == 0 or tx_kb == 0:
            continue
        print("%-6s %-12.12s %-21s %-21s %6d %6d" % (pid, comm, laddr, raddr, rx_kb, tx_kb))


def reboot_r6220(ip, password):
    request = Request('http://%s/setup.cgi?todo=debug' % ip)
    request.add_header('Authorization', 'Basic %s' % base64.b64encode(('admin:%s' % password).encode()).decode())
    for _ in xrange(3):
        try:
            resp = urlopen(request, timeout=2)
            logging.info('Enable %s debug return: %s', ip, resp.read())
            break
        except Exception as e:
            logging.error('Enable %s debug return: %s', ip, e)
            time.sleep(1)
    else:
        return
    for _ in xrange(3):
        try:
            logging.info('Begin telnet %s', ip)
            t = telnetlib.Telnet(ip, port=23, timeout=10)
            break
        except Exception as e:
            logging.error('telney %s return: %s', ip, e)
            time.sleep(1)
    else:
        return
    t.read_until('login:')
    t.write('root\n')
    resp = t.read_until('#')
    logging.info('telnet r6220 return: %s', resp)
    t.write('reboot\n')
    resp = t.read_until('#')
    logging.info('reboot r6220 return: %s', resp)
    t.close()


def __main():
    applet = os.path.basename(sys.argv[0])
    funcs = [v for v in globals().values() if type(v) is type(__main) and v.__module__ == '__main__' and not v.__name__.startswith('_')]
    if not PY3:
        for func in funcs:
            setattr(func, '__doc__', getattr(func, 'func_doc'))
            setattr(func, '__defaults__', getattr(func, 'func_defaults'))
            setattr(func, '__code__', getattr(func, 'func_code'))
    funcs = sorted(funcs, key=lambda x:x.__name__)
    params = {f.__name__:list(zip_longest(f.__code__.co_varnames[:f.__code__.co_argcount][::-1], (f.__defaults__ or [])[::-1]))[::-1] for f in funcs}
    def usage(applet):
        if applet == 'bb.py':
            print('Usage: {0} <applet> [arguments]\n\nExamples:\n{1}\n'.format(applet, '\n'.join('\t{0} {1} {2}'.format(applet, k, ' '.join('--{0} {1}'.format(x.replace('_', '-'), x.upper() if y is None else repr(y)) for (x, y) in v)) for k, v in params.items())))
        else:
            print('\nUsage:\n\t{0} {1}'.format(applet, ' '.join('--{0} {1}'.format(x.replace('_', '-'), x.upper() if y is None else repr(y)) for (x, y) in params[applet])))
    if '-h' in sys.argv or '--help' in sys.argv or (applet == 'bb.py' and not sys.argv[1:]):
        return usage(applet)
    if applet == 'bb.py':
        applet = sys.argv[1]
    for f in funcs:
        if f.__name__ == applet:
            break
    else:
        return usage()
    options = [x.replace('_','-')+'=' for x in f.__code__.co_varnames[:f.__code__.co_argcount]]
    kwargs, _ =  getopt.gnu_getopt(sys.argv[1:], '', options)
    kwargs = {k[2:].replace('-', '_'):v for k, v in kwargs}
    logging.debug('main %s(%s)', f.__name__, kwargs)
    try:
        result = f(**kwargs)
    except TypeError as e:
        patterns = [r'missing \d+ .* argument', r'takes (\w+ )+\d+ argument']
        if any(re.search(x, str(e)) for x in patterns):
            return usage(applet)
        raise
    if type(result) == type(b''):
        result = result.decode().strip()
    if result:
        print(result)


if __name__ == '__main__':
    __main()


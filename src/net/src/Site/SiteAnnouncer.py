import random
import time
import hashlib
import urllib
import urllib2
import struct
import socket

from lib import bencode
from lib.subtl.subtl import UdpTrackerClient
from lib.PySocks import socks
from lib.PySocks import sockshandler
import gevent

from Plugin import PluginManager
from Config import config
import util
from Debug import Debug


class AnnounceError(Exception):
    pass


@PluginManager.acceptPlugins
class SiteAnnouncer(object):
    def __init__(self, site):
        self.site = site
        self.stats = {}
        self.fileserver_port = config.fileserver_port
        self.peer_id = self.site.connection_server.peer_id
        self.last_tracker_id = random.randint(0, 10)
        self.time_last_announce = 0

    def getSupportedTrackers(self):
        trackers = config.trackers
        if config.disable_udp or config.trackers_proxy != "disable":
            trackers = [tracker for tracker in trackers if not tracker.startswith("udp://")]

        if not self.site.connection_server.tor_manager.enabled:
            trackers = [tracker for tracker in trackers if ".onion" not in tracker]

        return trackers

    def getAnnouncingTrackers(self, mode):
        trackers = self.getSupportedTrackers()

        if trackers and (mode == "update" or mode == "more"):  # Only announce on one tracker, increment the queried tracker id
            self.last_tracker_id += 1
            self.last_tracker_id = self.last_tracker_id % len(trackers)
            trackers_announcing = [trackers[self.last_tracker_id]]  # We only going to use this one
        else:
            trackers_announcing = trackers

        return trackers_announcing

    def getOpenedServiceTypes(self):
        back = []
        # Type of addresses they can reach me
        if self.site.connection_server.port_opened and config.trackers_proxy == "disable":
            back.append("ip4")
        if self.site.connection_server.tor_manager.start_onions:
            back.append("onion")
        return back

    @util.Noparallel(blocking=False)
    def announce(self, force=False, mode="start", pex=True):
        if time.time() < self.time_last_announce + 30 and not force:
            return  # No reannouncing within 30 secs

        self.fileserver_port = config.fileserver_port
        self.time_last_announce = time.time()

        trackers = self.getAnnouncingTrackers(mode)

        self.site.log.debug("Tracker announcing, trackers: %s" % trackers)

        errors = []
        slow = []
        s = time.time()
        threads = []
        num_announced = 0

        for tracker in trackers:  # Start announce threads
            thread = gevent.spawn(self.announceTracker, tracker, mode=mode)
            threads.append(thread)
            thread.tracker = tracker

        time.sleep(0.01)
        self.updateWebsocket(trackers="announcing")

        gevent.joinall(threads, timeout=20)  # Wait for announce finish

        for thread in threads:
            if thread.value is not False:
                if thread.value > 1.0:  # Takes more than 1 second to announce
                    slow.append("%.2fs %s" % (thread.value, thread.tracker))
                num_announced += 1
            else:
                if thread.ready():
                    errors.append(thread.tracker)
                else:  # Still running
                    slow.append("30s+ %s" % thread.tracker)

        # Save peers num
        self.site.settings["peers"] = len(self.site.peers)

        if len(errors) < len(threads):  # At least one tracker finished
            if len(trackers) == 1:
                announced_to = trackers[0]
            else:
                announced_to = "%s/%s trackers" % (num_announced, len(threads))
            if config.verbose or 1 == 1:  # remove
                self.site.log.debug(
                    "Announced in mode %s to %s in %.3fs, errors: %s, slow: %s" %
                    (mode, announced_to, time.time() - s, errors, slow)
                )
        else:
            if len(threads) > 1:
                self.site.log.error("Announce to %s trackers in %.3fs, failed" % (num_announced, time.time() - s))

        self.updateWebsocket(trackers="announced")

        if pex:
            self.updateWebsocket(pex="announcing")
            if mode == "more":  # Need more peers
                self.announcePex(need_num=10)
            else:
                self.announcePex()

            self.updateWebsocket(pex="announced")

    def getTrackerHandler(self, protocol):
        if protocol == "udp":
            handler = self.announceTrackerUdp
        elif protocol == "http":
            handler = self.announceTrackerHttp
        else:
            handler = None
        return handler

    def announceTracker(self, tracker, mode="start", num_want=10):
        s = time.time()
        protocol, address = tracker.split("://")
        if tracker not in self.stats:
            self.stats[tracker] = {"status": "", "num_request": 0, "num_success": 0, "num_error": 0, "time_request": 0}

        self.stats[tracker]["status"] = "announcing"
        self.stats[tracker]["time_status"] = time.time()
        self.stats[tracker]["num_request"] += 1
        self.site.log.debug("Tracker announcing to %s (mode: %s)" % (tracker, mode))
        if mode == "update":
            num_want = 10
        else:
            num_want = 30

        handler = self.getTrackerHandler(protocol)
        error = None
        try:
            if handler:
                peers = handler(address, mode=mode, num_want=num_want)
            else:
                raise AnnounceError("Unknown protocol: %s" % protocol)
        except Exception, err:
            self.site.log.warning("Tracker %s announce failed: %s" % (tracker, err))
            error = err

        if error:
            self.stats[tracker]["status"] = "error"
            self.stats[tracker]["time_status"] = time.time()
            self.stats[tracker]["last_error"] = str(err)
            self.stats[tracker]["num_error"] += 1
            self.updateWebsocket(tracker="error")
            return False

        self.stats[tracker]["status"] = "announced"
        self.stats[tracker]["time_status"] = time.time()
        self.stats[tracker]["num_success"] += 1
        self.updateWebsocket(tracker="success")

        if peers is None:  # No peers returned
            return time.time() - s

        # Adding peers
        added = 0
        for peer in peers:
            if peer["port"] == 1:  # Some trackers does not accept port 0, so we send port 1 as not-connectable
                peer["port"] = 0
            if not peer["port"]:
                continue  # Dont add peers with port 0
            if self.site.addPeer(peer["addr"], peer["port"], source="tracker"):
                added += 1

        if added:
            self.site.worker_manager.onPeers()
            self.site.updateWebsocket(peers_added=added)

        self.site.log.debug(
            "Tracker result: %s://%s (found %s peers, new: %s, total: %s)" %
            (protocol, address, len(peers), added, len(self.site.peers))
        )
        return time.time() - s

    def announceTrackerUdp(self, tracker_address, mode="start", num_want=10):
        s = time.time()
        if config.disable_udp:
            raise AnnounceError("Udp disabled by config")
        if config.trackers_proxy != "disable":
            raise AnnounceError("Udp trackers not available with proxies")

        ip, port = tracker_address.split(":")
        tracker = UdpTrackerClient(ip, int(port))
        if "ipv4" in self.getOpenedServiceTypes():
            tracker.peer_port = self.fileserver_port
        else:
            tracker.peer_port = 0
        tracker.connect()
        tracker.poll_once()
        tracker.announce(info_hash=hashlib.sha1(self.site.address).hexdigest(), num_want=num_want, left=431102370)
        back = tracker.poll_once()
        if not back:
            raise AnnounceError("No response after %.0fs" % (time.time() - s))
        elif type(back) is dict and "response" in back:
            peers = back["response"]["peers"]
        else:
            raise AnnounceError("Invalid response: %r" % back)

        return peers

    def httpRequest(self, url):
        if config.trackers_proxy == "tor":
            tor_manager = self.site.connection_server.tor_manager
            handler = sockshandler.SocksiPyHandler(socks.SOCKS5, tor_manager.proxy_ip, tor_manager.proxy_port)
            opener = urllib2.build_opener(handler)
            return opener.open(url, timeout=50)
        else:
            return urllib2.urlopen(url, timeout=25)

    def announceTrackerHttp(self, tracker_address, mode="start", num_want=10):
        if "ipv4" in self.getOpenedServiceTypes():
            port = self.fileserver_port
        else:
            port = 0
        params = {
            'info_hash': hashlib.sha1(self.site.address).digest(),
            'peer_id': self.peer_id, 'port': port,
            'uploaded': 0, 'downloaded': 0, 'left': 431102370, 'compact': 1, 'numwant': num_want,
            'event': 'started'
        }

        url = "http://" + tracker_address + "?" + urllib.urlencode(params)

        s = time.time()
        response = None
        # Load url
        if config.tor == "always" or config.trackers_proxy != "disable":
            timeout = 60
        else:
            timeout = 30

        with gevent.Timeout(timeout, False):  # Make sure of timeout
            req = self.httpRequest(url)
            response = req.read()
            req.fp._sock.recv = None  # Hacky avoidance of memory leak for older python versions
            req.close()
            req = None

        if not response:
            raise AnnounceError("No response after %.0fs" % (time.time() - s))

        # Decode peers
        try:
            peer_data = bencode.decode(response)["peers"]
            response = None
            peer_count = len(peer_data) / 6
            peers = []
            for peer_offset in xrange(peer_count):
                off = 6 * peer_offset
                peer = peer_data[off:off + 6]
                addr, port = struct.unpack('!LH', peer)
                peers.append({"addr": socket.inet_ntoa(struct.pack('!L', addr)), "port": port})
        except Exception as err:
            raise AnnounceError("Invalid response: %r (%s)" % (response, err))

        return peers

    @util.Noparallel(blocking=False)
    def announcePex(self, query_num=2, need_num=5):
        peers = self.site.getConnectedPeers()
        if len(peers) == 0:  # Wait 3s for connections
            time.sleep(3)
            peers = self.site.getConnectedPeers()

        if len(peers) == 0:  # Small number of connected peers for this site, connect to any
            peers = self.site.peers.values()
            need_num = 10

        random.shuffle(peers)
        done = 0
        total_added = 0
        for peer in peers:
            num_added = peer.pex(need_num=need_num)
            if num_added is not False:
                done += 1
                total_added += num_added
                if num_added:
                    self.site.worker_manager.onPeers()
                    self.site.updateWebsocket(peers_added=num_added)
            if done == query_num:
                break
        self.site.log.debug("Pex result: from %s peers got %s new peers." % (done, total_added))

    def updateWebsocket(self, **kwargs):
        if kwargs:
            param = {"event": kwargs.items()[0]}
        else:
            param = None

        for ws in self.site.websockets:
            ws.event("announcerChanged", self.site, param)

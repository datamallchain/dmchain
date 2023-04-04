import logging
import time
import array

import gevent

from cStringIO import StringIO
from Debug import Debug
from Config import config
from util import helper
from PeerHashfield import PeerHashfield

if config.use_tempfiles:
    import tempfile


# Communicate remote peers
class Peer(object):
    __slots__ = (
        "ip", "port", "site", "key", "connection", "last_found", "last_response", "last_ping", "last_hashfield",
        "hashfield", "added", "connection_error", "hash_failed", "download_bytes", "download_time"
    )

    def __init__(self, ip, port, site=None):
        self.ip = ip
        self.port = port
        self.site = site
        self.key = "%s:%s" % (ip, port)

        self.connection = None
        self.hashfield = PeerHashfield()  # Got optional files hash_id
        self.last_hashfield = 0  # Last time hashfiled downloaded
        self.last_found = time.time()  # Time of last found in the torrent tracker
        self.last_response = None  # Time of last successful response from peer
        self.last_ping = None  # Last response time for ping
        self.added = time.time()

        self.connection_error = 0  # Series of connection error
        self.hash_failed = 0  # Number of bad files from peer
        self.download_bytes = 0  # Bytes downloaded
        self.download_time = 0  # Time spent to download

    def log(self, text):
        if self.site:
            self.site.log.debug("%s:%s %s" % (self.ip, self.port, text))
        else:
            logging.debug("%s:%s %s" % (self.ip, self.port, text))

    # Connect to host
    def connect(self, connection=None):
        if self.connection:
            self.log("Getting connection (Closing %s)..." % self.connection)
            self.connection.close()
        else:
            self.log("Getting connection...")

        if connection:  # Connection specified
            self.connection = connection
        else:  # Try to find from connection pool or create new connection
            self.connection = None

            try:
                self.connection = self.site.connection_server.getConnection(self.ip, self.port)
            except Exception, err:
                self.onConnectionError()
                self.log("Getting connection error: %s (connection_error: %s, hash_failed: %s)" %
                         (Debug.formatException(err), self.connection_error, self.hash_failed))
                self.connection = None

    # Check if we have connection to peer
    def findConnection(self):
        if self.connection and self.connection.connected:  # We have connection to peer
            return self.connection
        else:  # Try to find from other sites connections
            self.connection = self.site.connection_server.getConnection(self.ip, self.port, create=False)
        return self.connection

    def __str__(self):
        return "Peer:%-12s" % self.ip

    def __repr__(self):
        return "<%s>" % self.__str__()

    def packMyAddress(self):
        return helper.packAddress(self.ip, self.port)

    # Found a peer on tracker
    def found(self):
        self.last_found = time.time()

    # Send a command to peer
    def request(self, cmd, params={}, stream_to=None):
        if not self.connection or self.connection.closed:
            self.connect()
            if not self.connection:
                self.onConnectionError()
                return None  # Connection failed

        for retry in range(1, 3):  # Retry 3 times
            try:
                response = self.connection.request(cmd, params, stream_to)
                if not response:
                    raise Exception("Send error")
                if "error" in response:
                    self.log("%s error: %s" % (cmd, response["error"]))
                    self.onConnectionError()
                else:  # Successful request, reset connection error num
                    self.connection_error = 0
                self.last_response = time.time()
                return response
            except Exception, err:
                if type(err).__name__ == "Notify":  # Greenlet killed by worker
                    self.log("Peer worker got killed: %s, aborting cmd: %s" % (err.message, cmd))
                    break
                else:
                    self.onConnectionError()
                    self.log(
                        "%s (connection_error: %s, hash_failed: %s, retry: %s)" %
                        (Debug.formatException(err), self.connection_error, self.hash_failed, retry)
                    )
                    time.sleep(1 * retry)
                    self.connect()
        return None  # Failed after 4 retry

    # Get a file content from peer
    def getFile(self, site, inner_path):
        # Use streamFile if client supports it
        if config.stream_downloads and self.connection and self.connection.handshake and self.connection.handshake["rev"] > 310:
            return self.streamFile(site, inner_path)

        location = 0
        if config.use_tempfiles:
            buff = tempfile.SpooledTemporaryFile(max_size=16 * 1024, mode='w+b')
        else:
            buff = StringIO()

        s = time.time()
        while True:  # Read in 512k parts
            back = self.request("getFile", {"site": site, "inner_path": inner_path, "location": location})

            if not back or "body" not in back:  # Error
                return False

            buff.write(back["body"])
            back["body"] = None  # Save memory
            if back["location"] == back["size"]:  # End of file
                break
            else:
                location = back["location"]

        self.download_bytes += back["location"]
        self.download_time += (time.time() - s)
        self.site.settings["bytes_recv"] = self.site.settings.get("bytes_recv", 0) + back["location"]
        buff.seek(0)
        return buff

    # Download file out of msgpack context to save memory and cpu
    def streamFile(self, site, inner_path):
        location = 0
        if config.use_tempfiles:
            buff = tempfile.SpooledTemporaryFile(max_size=16 * 1024, mode='w+b')
        else:
            buff = StringIO()

        s = time.time()
        while True:  # Read in 512k parts
            back = self.request("streamFile", {"site": site, "inner_path": inner_path, "location": location}, stream_to=buff)

            if not back:  # Error
                self.log("Invalid response: %s" % back)
                return False

            if back["location"] == back["size"]:  # End of file
                break
            else:
                location = back["location"]

        self.download_bytes += back["location"]
        self.download_time += (time.time() - s)
        self.site.settings["bytes_recv"] = self.site.settings.get("bytes_recv", 0) + back["location"]
        buff.seek(0)
        return buff

    # Send a ping request
    def ping(self):
        response_time = None
        for retry in range(1, 3):  # Retry 3 times
            s = time.time()
            with gevent.Timeout(10.0, False):  # 10 sec timeout, don't raise exception
                response = self.request("ping")

                if response and "body" in response and response["body"] == "Pong!":
                    response_time = time.time() - s
                    break  # All fine, exit from for loop
            # Timeout reached or bad response
            self.onConnectionError()
            self.connect()
            time.sleep(1)

        if response_time:
            self.log("Ping: %.3f" % response_time)
        else:
            self.log("Ping failed")
        self.last_ping = response_time
        return response_time

    # Request peer exchange from peer
    def pex(self, site=None, need_num=5):
        if not site:
            site = self.site  # If no site defined request peers for this site
        # give him/her 5 connectible peers
        packed_peers = [peer.packMyAddress() for peer in self.site.getConnectablePeers(5)]
        response = self.request("pex", {"site": site.address, "peers": packed_peers, "need": need_num})
        if not response or "error" in response:
            return False
        added = 0
        for peer in response.get("peers", []):
            address = helper.unpackAddress(peer)
            if site.addPeer(*address):
                added += 1
        if added:
            self.log("Added peers using pex: %s" % added)
        return added

    # Request optional files hashfield from peer
    def getHashfield(self):
        self.last_hashfield = time.time()
        res = self.request("getHashfield", {"site": self.site.address})
        if res and "error" not in res:
            self.hashfield.replaceFromString(res["hashfield_raw"])
            return self.hashfield
        else:
            return False

    # List modified files since the date
    # Return: {inner_path: modification date,...}
    def listModified(self, since):
        return self.request("listModified", {"since": since, "site": self.site.address})

    # Stop and remove from site
    def remove(self):
        self.log("Removing peer...Connection error: %s, Hash failed: %s" % (self.connection_error, self.hash_failed))
        if self.site and self.key in self.site.peers:
            del(self.site.peers[self.key])
        if self.connection:
            self.connection.close()

    # - HASHFIELD -

    def updateHashfield(self, force=False):
        # Don't update hashfield again in 15 min
        if self.last_hashfield and time.time() - self.last_hashfield > 60 * 15 and not force:
            return False

        response = self.request("getHashfield", {"site": self.site.address})
        if not response or "error" in response:
            return False
        self.last_hashfield = time.time()
        self.hashfield = response["hashfield"]

        return self.hashfield

    def setHashfield(self, hashfield_dump):
        self.hashfield.fromstring(hashfield_dump)

    def hasHash(self, hash_id):
        return hash_id in self.hashfield

    # Return: ["ip:port", "ip:port",...]
    def findHash(self, hash_id):
        response = self.request("findHash", {"site": self.site.address, "hash_id": hash_id})
        if not response or "error" in response:
            return False
        return [helper.unpackAddress(peer) for peer in response["peers"]]

    # - EVENTS -

    # On connection error
    def onConnectionError(self):
        self.connection_error += 1
        if self.connection_error >= 3:  # Dead peer
            self.remove()

    # Done working with peer
    def onWorkerDone(self):
        pass

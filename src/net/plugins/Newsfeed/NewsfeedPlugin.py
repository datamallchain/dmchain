from Plugin import PluginManager
import re

@PluginManager.registerTo("UiWebsocket")
class UiWebsocketPlugin(object):
    def actionFeedFollow(self, to, feeds):
        self.user.setFeedFollow(self.site.address, feeds)
        self.response(to, "ok")

    def actionFeedListFollow(self, to):
        feeds = self.user.sites[self.site.address].get("follow", {})
        self.response(to, feeds)

    def actionFeedQuery(self, to):
        from Site import SiteManager
        rows = []
        for address, site_data in self.user.sites.iteritems():
            feeds = site_data.get("follow")
            if not feeds:
                continue
            for name, query_set in feeds.iteritems():
                site = SiteManager.site_manager.get(address)
                try:
                    query, params = query_set
                    if ":params" in query:
                        query = query.replace(":params", ",".join(["?"]*len(params)))
                        res = site.storage.query(query+" ORDER BY date_added DESC LIMIT 10", params)
                    else:
                        res = site.storage.query(query+" ORDER BY date_added DESC LIMIT 10")
                except Exception, err:  # Log error
                    self.log.error("%s feed query %s error: %s" % (address, name, err))
                    continue
                for row in res:
                    row = dict(row)
                    row["site"] = address
                    row["feed_name"] = name
                    rows.append(row)
        return self.response(to, rows)


@PluginManager.registerTo("User")
class UserPlugin(object):
    # Set queries that user follows
    def setFeedFollow(self, address, feeds):
        site_data = self.getSiteData(address)
        site_data["follow"] = feeds
        self.save()
        return site_data

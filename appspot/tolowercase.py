import webapp2

class LowerCase(webapp2.RequestHandler):
    def get(self, path):
        self.redirect("/" + path.lower(), True)

application = webapp2.WSGIApplication([
    ('/([-A-Za-z0-9]+)', LowerCase),
])

function noEmailsResponse(res) {
  return res.status(429).json({
    success: false,
    error: 'no_emails_available',
    message: 'No available emails at this time',
    timestamp: Math.floor(Date.now() / 1000)
  });
}

export function registerApiRoutes(app, store) {
  const handler = (req, res, next) => {
    try {
      const email = store.getRandomEmail();
      if (!email) return noEmailsResponse(res);

      return res.json({
        success: true,
        email,
        timestamp: Math.floor(Date.now() / 1000)
      });
    } catch (error) {
      return next(error);
    }
  };

  app.get('/api/email', handler);
  app.get('/api/email.php', handler);
}

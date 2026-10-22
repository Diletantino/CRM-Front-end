ARG ESPOCRM_VERSION=10.0.9
FROM espocrm/espocrm:${ESPOCRM_VERSION}-apache

USER root

COPY --chown=root:root custom/Espo/Modules/StudioManagement/ /opt/studio-management/server/
COPY --chown=root:root client/custom/modules/studio-management/ /opt/studio-management/client/
COPY --chown=www-data:www-data client/res/templates/site/footer.tpl /usr/src/espocrm/client/res/templates/site/footer.tpl
COPY --chown=www-data:www-data client/res/templates/site/footer.tpl /var/www/html/client/res/templates/site/footer.tpl
COPY --chown=www-data:www-data html/main.html /usr/src/espocrm/html/main.html
COPY --chown=www-data:www-data html/main.html /var/www/html/html/main.html
COPY --chmod=755 docker/runtime/studio-entrypoint.sh /usr/local/bin/studio-entrypoint.sh
COPY --chmod=755 docker/runtime/apache2-studio-bootstrap /usr/local/bin/apache2-studio-bootstrap

ENTRYPOINT ["studio-entrypoint.sh"]
CMD ["apache2-foreground"]

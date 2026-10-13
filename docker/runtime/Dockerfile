ARG ESPOCRM_VERSION=10.0.9
FROM espocrm/espocrm:${ESPOCRM_VERSION}-apache

USER root

COPY --chown=root:root custom/Espo/Modules/StudioManagement/ /opt/studio-management/server/
COPY --chown=root:root client/custom/modules/studio-management/ /opt/studio-management/client/
COPY --chmod=755 docker/runtime/studio-entrypoint.sh /usr/local/bin/studio-entrypoint.sh

ENTRYPOINT ["studio-entrypoint.sh"]
CMD ["apache2-foreground"]

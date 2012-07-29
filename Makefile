# Sysconf - Interactive configuration system for PHP applications
# Copyright (C) 2012 by iHMS Team
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
# copyright   2012 by iHMS Team
# author      Laurent Declercq <l.declercq@nuxwin.com>
# version     0.0.1
# link        https://github.com/i-HMS/sysconf Sysconf Home Site
# license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2

export prefix ?= /usr/local

LIBMOD=$(sort $(shell find library -type f -name \*.in))
LIB=$(LIBMOD:.in=.php)

PROGMOD=$(sort $(shell find . -maxdepth 1 -perm +100 -type f -name sysconf-\*.in -or -name ihms-\*.in))
PROG=$(PROGMOD:.in=)

all: $(LIB) $(PROG) confmodule frontend samples/demo sysconf.conf
	$(MAKE) -C po
	$(MAKE) -C doc

install: install-utils install-rest install-i18n install-doc

# Anything that goes in the ihms-sysconf-utils package.
install-utils: $(PROG)
	install -d $(DESTDIR)$(prefix)/bin
	find $^ -name sysconf-\* | grep -v frontend | grep -v sysconf-set-selections | grep -v sysconf-show | \
		grep -v sysconf-copydb | grep -v sysconf-communicate | xargs -i install {} $(DESTDIR)$(prefix)/bin

# Anything that goes in the ihms-sysconf-i18n package
install-i18n:
	$(MAKE) -C po install

install-doc:
	$(MAKE) -C doc install

# Install all else
install-rest: $(LIB) $(PROG) confmodule frontend sysconf.conf
	install -d \
		$(DESTDIR)$(prefix)/etc/ihms/ \
		$(DESTDIR)$(prefix)/share/ihms/sysconf \
		$(DESTDIR)$(prefix)/var/cache/ihms/sysconf \
		$(DESTDIR)$(prefix)/bin \
		$(DESTDIR)$(prefix)/sbin
	# Install sysconf configuration file
	install -m 0644 sysconf.conf $(DESTDIR)$(prefix)/etc/ihms
	# This one is the ultimate backup copy
	grep -v '^#' sysconf.conf > $(DESTDIR)$(prefix)/share/ihms/sysconf/sysconf.conf
	# Install sysconf library
	find library -type d | xargs -i install -d $(DESTDIR)$(prefix)/share/ihms/{}
	find library -type f -name \*.php | xargs -i install -m 0644 {} $(DESTDIR)$(prefix)/share/ihms/{}
	# install bootstrap, frontend helper and ini file
	install confmodule bootstrap.php frontend $(DESTDIR)$(prefix)/share/ihms/sysconf
	# Install essential program
	find $^ -maxdepth 1 -perm +100 -type f -name ihms-\* | xargs -i install {} $(DESTDIR)/$(prefix)/sbin
	find $^ -maxdepth 1 -perm +100 -type f -name sysconf-show -or -name sysconf-copydb -or -name sysconf-communicate \
		-or -name sysconf-set-selections | xargs -i install {} $(DESTDIR)$(prefix)/bin

demo: $(LIB) sysconf.conf frontend samples/demo
	SYSCONF_SYSTEMRC="$(shell pwd)/sysconf.conf" SYSCONF_FRONTEND=dialog ./frontend samples/demo

%: %.in
	sed -e "s|@prefix@|$(prefix)|g" < $< > $@
	chmod $(shell stat -c "0%a" $<) $@

%.php: %.in
	sed -e "s|@prefix@|$(prefix)|g" < $< > $@
	chmod $(shell stat -c "0%a" $<) $@

sysconf.conf:
	# Build sysconf configuration file
	sed -e "s|@prefix@|$(prefix)|g" < sysconf.in > sysconf.conf;

uninstall:
	$(MAKE) -C po uninstall
	$(MAKE) -C doc uninstall
	$(RM) $(DESTDIR)$(prefix)/etc/ihms/sysconf.conf
	$(RM) -R $(DESTDIR)$(prefix)/share/ihms/library/iHMS/Sysconf $(DESTDIR)$(prefix)/share/ihms/sysconf
	$(RM) $(DESTDIR)$(prefix)/sbin/ihms-* $(DESTDIR)$(prefix)/bin/sysconf-*
	$(RM) -r $(DESTDIR)$(prefix)/var/cache/ihms/sysconf
	find $(DESTDIR)$(prefix)/share/ihms -depth -type d -empty | xargs -i rm -R {}


distclean: clean
clean:
	$(MAKE) -C po clean
	$(MAKE) -C doc clean
	$(RM) $(LIB) $(PROG) samples/demo frontend sysconf.conf

PHONY: clean

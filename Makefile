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
export bindir ?= $(prefix)/bin
export sbindir ?= $(prefix)/sbin
export sysconfdir ?= $(prefix)/etc
export localstatedir ?= $(prefix)/var
export datarootdir ?= $(prefix)/share
export datadir ?= $(datarootdir)
export localedir ?= $(datarootdir)/locale
export mandir ?= $(datarootdir)/man
export docdir ?= $(datarootdir)/doc/ihms-sysconf

LIBMOD=$(sort $(shell find library -type f -name \*.in))
LIB=$(LIBMOD:.in=.php)

PROGMOD=$(sort $(shell find . -maxdepth 1 -perm +100 -type f -name sysconf-\*.in -or -name ihms-\*.in))
PROG=$(PROGMOD:.in=)

all: $(LIB) $(PROG) bootstrap.php confmodule frontend samples/demo sysconf.conf
	$(MAKE) -C po
	$(MAKE) -C doc

install: install-utils install-rest install-i18n install-doc

# Anything that goes in the ihms-sysconf-utils package.
install-utils: $(PROG)
	install -d $(DESTDIR)$(bindir)
	find $^ -name sysconf-\* | grep -v frontend | grep -v sysconf-set-selections | grep -v sysconf-show | \
		grep -v sysconf-copydb | grep -v sysconf-communicate | xargs -i install {} $(DESTDIR)$(bindir)

# Anything that goes in the ihms-sysconf-i18n package
install-i18n:
	$(MAKE) -C po install

install-doc:
	$(MAKE) -C doc install

# Install all else
install-rest: $(LIB) $(PROG) bootstrap.php confmodule frontend sysconf.conf
	install -d \
		$(DESTDIR)$(sysconfdir)/ihms \
		$(DESTDIR)$(datadir)/ihms/sysconf \
		$(DESTDIR)$(localstatedir)/cache/ihms/sysconf \
		$(DESTDIR)$(bindir) \
		$(DESTDIR)$(sbindir)
	# Install sysconf configuration file
	install -m 0644 sysconf.conf $(DESTDIR)$(sysconfdir)/ihms
	# This one is the ultimate backup copy
	grep -v '^#' sysconf.conf > $(DESTDIR)$(datadir)/ihms/sysconf/sysconf.conf
	# Install sysconf library
	find library -type d | xargs -i install -d $(DESTDIR)$(datadir)/ihms/{}
	find library -type f -name \*.php | xargs -i install -m 0644 {} $(DESTDIR)$(datadir)/ihms/{}
	# install bootstrap, frontend helper and ini file
	install confmodule bootstrap.php frontend $(DESTDIR)$(datadir)/ihms/sysconf
	# Install essential program
	find $^ -maxdepth 1 -perm +100 -type f -name ihms-\* | xargs -i install {} $(DESTDIR)$(sbindir)
	find $^ -maxdepth 1 -perm +100 -type f -name sysconf-show -or -name sysconf-copydb -or -name sysconf-communicate \
		-or -name sysconf-set-selections | xargs -i install {} $(DESTDIR)$(bindir)

demo: $(LIB) bootstrap.php frontend samples/demo sysconf.conf
	SYSCONF_SYSTEMRC="$(shell pwd)/sysconf.conf" SYSCONF_FRONTEND=dialog ./frontend samples/demo

%: %.in
	sed -e "s|@datadir@|$(datadir)|g" -e "s|@sysconfdir@|$(sysconfdir)|g" < $< > $@
	chmod $(shell stat -c "0%a" $<) $@

%.php: %.in
	sed -e "s|@sysconfdir@|$(sysconfdir)|g" -e "s|@datadir@|$(datadir)|g" -e "s|@localedir@|$(localedir)|g" < $< > $@
	chmod $(shell stat -c "0%a" $<) $@

sysconf.conf:
	# Build sysconf configuration file
	sed -e "s|@localstatedir@|$(localstatedir)|g" < sysconf.in > sysconf.conf;

uninstall:
	$(MAKE) -C po uninstall
	$(MAKE) -C doc uninstall
	$(RM) $(sysconfdir)/ihms/sysconf.conf
	$(RM) -R $(datadir)/ihms/library/iHMS/Sysconf $(DESTDIR)$(datadir)/ihms/sysconf
	$(RM) $(bindir)/sysconf-* $(DESTDIR)$(sbindir)/ihms-*
	$(RM) -r $(localstatedir)/cache/ihms/sysconf
	find $(datadir)/share/ihms -depth -type d -empty | xargs -i rm -R {}

distclean: clean
clean:
	$(MAKE) -C po clean
	$(MAKE) -C doc clean
	$(RM) $(LIB) $(PROG) bootstrap.php frontend samples/demo sysconf.conf

PHONY: clean

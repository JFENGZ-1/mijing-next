(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-index-list/u-index-list"],
  {
    "2f7a": function (t, e, n) {},
    3133: function (t, e, n) {
      "use strict";
      var i = n("2f7a");
      n.n(i).a;
    },
    3734: function (t, e, n) {
      "use strict";
      n.r(e);
      var i = n("ddc2"),
        o = n.n(i);
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return i[t];
            });
          })(r);
      e.default = o.a;
    },
    "698c": function (t, e, n) {
      "use strict";
      n.d(e, "b", function () {
        return i;
      }),
        n.d(e, "c", function () {
          return o;
        }),
        n.d(e, "a", function () {});
      var i = function () {
          this.$createElement;
          this._self._c;
        },
        o = [];
    },
    a5e6: function (t, e, n) {
      "use strict";
      n.r(e);
      var i = n("698c"),
        o = n("3734");
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return o[t];
            });
          })(r);
      n("3133");
      var c = n("828b"),
        a = Object(c.a)(
          o.default,
          i.b,
          i.c,
          !1,
          null,
          "022e31b5",
          null,
          !1,
          i.a,
          void 0,
        );
      e.default = a.exports;
    },
    ddc2: function (t, e, n) {
      "use strict";
      (function (t) {
        var i = n("47a9");
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var o = i(n("7eb4")),
          r = i(n("ee10")),
          c = {
            name: "u-index-list",
            props: {
              sticky: { type: Boolean, default: !0 },
              zIndex: { type: [Number, String], default: "" },
              scrollTop: { type: [Number, String], default: 0 },
              offsetTop: { type: [Number, String], default: 0 },
              indexList: {
                type: Array,
                default: function () {
                  return (function () {
                    for (var t = [], e = "A".charCodeAt(0), n = 0; n < 26; n++)
                      t.push(String.fromCharCode(e + n));
                    return t;
                  })();
                },
              },
              StatusBar: { type: [Number, String], default: 0 },
              activeColor: { type: String, default: "#22C788" },
            },
            created: function () {
              (this.stickyOffsetTop = this.offsetTop
                ? t.upx2px(this.offsetTop)
                : 0),
                (this.children = []);
            },
            data: function () {
              return {
                activeAnchorIndex: 0,
                showSidebar: !0,
                touchmove: !1,
                touchmoveIndex: 0,
              };
            },
            watch: {
              scrollTop: function () {
                this.updateData();
              },
            },
            computed: {
              alertZIndex: function () {
                return this.$u.zIndex.toast;
              },
            },
            methods: {
              updateData: function () {
                var t = this;
                this.timer && clearTimeout(this.timer),
                  (this.timer = setTimeout(function () {
                    (t.showSidebar = !!t.children.length),
                      t.setRect().then(function () {
                        t.onScroll();
                      });
                  }, 50));
              },
              setRect: function () {
                return Promise.all([
                  this.setAnchorsRect(),
                  this.setListRect(),
                  this.setSiderbarRect(),
                ]);
              },
              setAnchorsRect: function () {
                return Promise.all(
                  this.children.map(function (t, e) {
                    return t
                      .$uGetRect(".u-index-anchor-wrapper")
                      .then(function (e) {
                        Object.assign(t, { height: e.height, top: e.top });
                      });
                  }),
                );
              },
              setListRect: function () {
                var t = this;
                return this.$uGetRect(".u-index-bar").then(function (e) {
                  Object.assign(t, {
                    height: e.height,
                    top: e.top + t.scrollTop,
                  });
                });
              },
              setSiderbarRect: function () {
                var t = this;
                return this.$uGetRect(".u-index-bar__sidebar").then(
                  function (e) {
                    t.sidebar = { height: e.height, top: e.top };
                  },
                );
              },
              getActiveAnchorIndex: function () {
                for (
                  var t = this.children,
                    e = this.sticky,
                    n = this.children.length - 1;
                  n >= 0;
                  n--
                ) {
                  var i = n > 0 ? t[n - 1].height : 0;
                  if ((e ? i + 161 : 161) >= t[n].top) return n;
                }
                return -1;
              },
              onScroll: function () {
                var t = this,
                  e = this.children,
                  n = void 0 === e ? [] : e;
                if (n.length) {
                  var i = this.sticky,
                    o = this.stickyOffsetTop,
                    r = this.zIndex,
                    c = (this.scrollTop, this.activeColor),
                    a = this.getActiveAnchorIndex();
                  if (((this.activeAnchorIndex = a), i)) {
                    var s = !1;
                    -1 !== a && (s = n[a].top <= 0),
                      n.forEach(function (e, i) {
                        if (i === a) {
                          var u = "",
                            h = { color: "".concat(c) };
                          s &&
                            ((u = { height: "".concat(n[i].height, "px") }),
                            (h = {
                              position: "fixed",
                              top: "".concat(o, "px"),
                              zIndex: "".concat(
                                r || t.$u.zIndex.indexListSticky,
                              ),
                              color: "".concat(c),
                            })),
                            (e.active = a),
                            (e.wrapperStyle = u),
                            (e.anchorStyle = h);
                        } else if (i === a - 1) {
                          var l = n[i],
                            d = l.top,
                            f =
                              (i === n.length - 1 ? t.top : n[i + 1].top) -
                              d -
                              l.height,
                            p = {
                              position: "relative",
                              transform: "translate3d(0, ".concat(f, "px, 0)"),
                              zIndex: "".concat(
                                r || t.$u.zIndex.indexListSticky,
                              ),
                              color: "".concat(c),
                            };
                          (e.active = a), (e.anchorStyle = p);
                        } else
                          (e.active = !1),
                            (e.anchorStyle = ""),
                            (e.wrapperStyle = "");
                      });
                  }
                  this.activeIndex(this.activeAnchorIndex);
                }
              },
              activeIndex: function (t) {
                var e = this;
                return (0, r.default)(
                  o.default.mark(function n() {
                    return o.default.wrap(function (n) {
                      for (;;)
                        switch ((n.prev = n.next)) {
                          case 0:
                            try {
                              e.$emit("activeIndex", t);
                            } catch (t) {
                              console.error("Error fetching data:", t);
                            }
                          case 1:
                          case "end":
                            return n.stop();
                        }
                    }, n);
                  }),
                )();
              },
              onTouchMove: function (t) {
                this.touchmove = !0;
                var e,
                  n = this.children.length,
                  i = t.touches[0],
                  o = this.sidebar.height / n;
                e = i.clientY;
                var r = Math.floor((e - this.sidebar.top) / o);
                r < 0 ? (r = 0) : r > n - 1 && (r = n - 1),
                  (this.touchmoveIndex = r),
                  this.scrollToAnchor(r);
              },
              onTouchStop: function () {
                (this.touchmove = !1), (this.scrollToAnchorIndex = null);
              },
              scrollToAnchor: function (e) {
                var n = this;
                if (this.scrollToAnchorIndex !== e) {
                  this.scrollToAnchorIndex = e;
                  var i = this.children.find(function (t) {
                    return t.index === n.indexList[e];
                  });
                  if (i) {
                    var o = 50;
                    0 != this.StatusBar && (o = 0),
                      t.pageScrollTo({
                        duration: 0,
                        scrollTop: i.top + this.scrollTop + o,
                      });
                  }
                }
              },
              select: function (t) {
                var e = this;
                return (0, r.default)(
                  o.default.mark(function n() {
                    return o.default.wrap(function (n) {
                      for (;;)
                        switch ((n.prev = n.next)) {
                          case 0:
                            try {
                              e.$emit("select", t);
                            } catch (t) {
                              console.error("Error fetching data:", t);
                            }
                          case 1:
                          case "end":
                            return n.stop();
                        }
                    }, n);
                  }),
                )();
              },
            },
          };
        e.default = c;
      }).call(this, n("df3c").default);
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "uview-ui/components/u-index-list/u-index-list-create-component",
    {
      "uview-ui/components/u-index-list/u-index-list-create-component":
        function (t, e, n) {
          n("df3c").createComponent(n("a5e6"));
        },
    },
    [["uview-ui/components/u-index-list/u-index-list-create-component"]],
  ]);

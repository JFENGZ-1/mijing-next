require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/reminderSettings/index"],
    {
      "0b44": function (t, e, n) {
        "use strict";
        n.r(e);
        var i = n("1fc0"),
          o = n("9736");
        for (var r in o)
          ["default"].indexOf(r) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return o[t];
              });
            })(r);
        n("722b");
        var u = n("828b"),
          c = Object(u.a)(
            o.default,
            i.b,
            i.c,
            !1,
            null,
            "1e300f2e",
            null,
            !1,
            i.a,
            void 0,
          );
        e.default = c.exports;
      },
      "1fc0": function (t, e, n) {
        "use strict";
        n.d(e, "b", function () {
          return o;
        }),
          n.d(e, "c", function () {
            return r;
          }),
          n.d(e, "a", function () {
            return i;
          });
        var i = {
            uIcon: function () {
              return n
                .e("uview-ui/components/u-icon/u-icon")
                .then(n.bind(null, "81af"));
            },
            uSwitch: function () {
              return n
                .e("uview-ui/components/u-switch/u-switch")
                .then(n.bind(null, "a048"));
            },
            ffBottomLogo: function () {
              return n
                .e("components/ff-bottom-logo/ff-bottom-logo")
                .then(n.bind(null, "3111"));
            },
            ffPopup: function () {
              return n
                .e("components/ff-popup/ff-popup")
                .then(n.bind(null, "c29b"));
            },
            uButton: function () {
              return n
                .e("uview-ui/components/u-button/u-button")
                .then(n.bind(null, "d5d3"));
            },
          },
          o = function () {
            var t = this,
              e =
                (t.$createElement,
                t._self._c,
                t.imgsrc("/imgs/202510/add.png")),
              n = t.__map(t.teacherList, function (e, n) {
                return {
                  $orig: t.__get_orig(e),
                  m1: t.$shorten(e.staffName, 4),
                };
              });
            t.$mp.data = Object.assign({}, { $root: { m0: e, l0: n } });
          },
          r = [];
      },
      "722b": function (t, e, n) {
        "use strict";
        var i = n("b47a");
        n.n(i).a;
      },
      9736: function (t, e, n) {
        "use strict";
        n.r(e);
        var i = n("ec8c"),
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
      b47a: function (t, e, n) {},
      c785: function (t, e, n) {
        "use strict";
        (function (t, e) {
          var i = n("47a9");
          n("86d2"), i(n("3240"));
          var o = i(n("0b44"));
          (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(o.default);
        }).call(this, n("3223").default, n("df3c").createPage);
      },
      ec8c: function (t, e, n) {
        "use strict";
        (function (t) {
          var i = n("47a9");
          Object.defineProperty(e, "__esModule", { value: !0 }),
            (e.default = void 0);
          var o = i(n("7ca3")),
            r = n("f24f"),
            u = n("962b");
          function c(t, e) {
            var n = Object.keys(t);
            if (Object.getOwnPropertySymbols) {
              var i = Object.getOwnPropertySymbols(t);
              e &&
                (i = i.filter(function (e) {
                  return Object.getOwnPropertyDescriptor(t, e).enumerable;
                })),
                n.push.apply(n, i);
            }
            return n;
          }
          function a(t) {
            for (var e = 1; e < arguments.length; e++) {
              var n = null != arguments[e] ? arguments[e] : {};
              e % 2
                ? c(Object(n), !0).forEach(function (e) {
                    (0, o.default)(t, e, n[e]);
                  })
                : Object.getOwnPropertyDescriptors
                  ? Object.defineProperties(
                      t,
                      Object.getOwnPropertyDescriptors(n),
                    )
                  : c(Object(n)).forEach(function (e) {
                      Object.defineProperty(
                        t,
                        e,
                        Object.getOwnPropertyDescriptor(n, e),
                      );
                    });
            }
            return t;
          }
          i(n("3387"));
          var s = {
            name: "index",
            data: function () {
              return {
                isMust: !1,
                list: [],
                administrators: {},
                league: [],
                privates: [],
                showManagerPopup: !1,
                teacherList: [],
                selectedManagers: [],
              };
            },
            components: {
              navigation: function () {
                n.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(n("af9e"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var e = t.getMenuButtonBoundingClientRect();
                return (
                  e.height +
                  2 * (e.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            methods: {
              getList: function () {
                var t = this;
                (0, u.getHintSetting)().then(function (e) {
                  (t.list = e.configlist),
                    (t.administrators = [1, 15, 19, 18, 12, 14].reduce(
                      function (e, n) {
                        var i = t.list.find(function (t) {
                          return t.id === n;
                        });
                        return i && e.push(i), e;
                      },
                      [],
                    )),
                    (t.privates = t.list.filter(function (t) {
                      return [2, 4].includes(t.id);
                    })),
                    (t.league = [5, 7, 9, 8].reduce(function (e, n) {
                      var i = t.list.find(function (t) {
                        return t.id === n;
                      });
                      return i && e.push(i), e;
                    }, []));
                });
              },
              headleStatus: function (t, e) {
                var n = t ? 1 : 0;
                this.getSaveHintSetting(n, e);
              },
              getSaveHintSetting: function (e, n) {
                var i = this;
                (0, u.saveHintSetting)({ value: e, id: n }).then(function (n) {
                  var o = 1 == e ? "已开启" : "已关闭";
                  200 == n.code
                    ? (i.getList(), t.showToast({ icon: "none", title: o }))
                    : t.showToast({ icon: "none", title: n.msg });
                });
              },
              getSelectedManagers: function () {
                var t = this;
                (0, u.getHintManagerConfig)().then(function (e) {
                  200 == e.code && (t.selectedManagers = e.configlist || []);
                });
              },
              openManagerPopup: function () {
                var e = this;
                (0, r.getStaffInWorking)().then(function (n) {
                  if (200 == n.code) {
                    var i = e.selectedManagers.map(function (t) {
                      return t.staffUserid;
                    });
                    (e.teacherList = n.data.map(function (t) {
                      return a(
                        a({}, t),
                        {},
                        { selected: i.includes(t.staffUserid) },
                      );
                    })),
                      (e.showManagerPopup = !0);
                  } else t.showToast({ icon: "none", title: n.msg });
                });
              },
              toggleTeacher: function (t) {
                (t.selected = !t.selected), this.$forceUpdate();
              },
              confirmSelection: function () {
                var e = this,
                  n = this.teacherList.filter(function (t) {
                    return t.selected;
                  });
                if (0 !== n.length) {
                  var i = n.map(function (t) {
                    return t.staffUserid;
                  });
                  (0, u.saveHintManagerConfig)({ staffUserIdList: i }).then(
                    function (n) {
                      200 == n.code
                        ? ((e.showManagerPopup = !1),
                          e.getSelectedManagers(),
                          t.showToast({ icon: "success", title: "保存成功" }))
                        : t.showToast({ icon: "none", title: n.msg });
                    },
                  );
                } else
                  t.showToast({ icon: "none", title: "请至少选择一位老师" });
              },
            },
            onLoad: function () {
              this.getList(), this.getSelectedManagers();
            },
          };
          e.default = s;
        }).call(this, n("df3c").default);
      },
    },
    [["c785", "common/runtime", "common/vendor", "pageConfig/common/vendor"]],
  ]);

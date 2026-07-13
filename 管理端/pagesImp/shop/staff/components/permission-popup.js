(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/shop/staff/components/permission-popup"],
  {
    af00: function (e, n, t) {
      "use strict";
      (function (e) {
        var i = t("47a9");
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var c = i(t("af34")),
          o = i(t("7ca3")),
          u = t("f24f");
        function s(e, n) {
          var t = Object.keys(e);
          if (Object.getOwnPropertySymbols) {
            var i = Object.getOwnPropertySymbols(e);
            n &&
              (i = i.filter(function (n) {
                return Object.getOwnPropertyDescriptor(e, n).enumerable;
              })),
              t.push.apply(t, i);
          }
          return t;
        }
        function r(e) {
          for (var n = 1; n < arguments.length; n++) {
            var t = null != arguments[n] ? arguments[n] : {};
            n % 2
              ? s(Object(t), !0).forEach(function (n) {
                  (0, o.default)(e, n, t[n]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    e,
                    Object.getOwnPropertyDescriptors(t),
                  )
                : s(Object(t)).forEach(function (n) {
                    Object.defineProperty(
                      e,
                      n,
                      Object.getOwnPropertyDescriptor(t, n),
                    );
                  });
          }
          return e;
        }
        var d = {
          data: function () {
            return {
              permissionShow: !1,
              pfunction: [],
              cfunction: [],
              permissionList: {},
            };
          },
          methods: {
            findAllFunction: function (e) {
              var n = this;
              this.permissionShow
                ? ((this.permissionShow = !1),
                  setTimeout(function () {
                    n.permissionShow = !0;
                  }, 10))
                : (this.permissionShow = !0),
                (this.permissionList = e),
                (this.pfunction = []),
                (this.cfunction = []),
                (0, u.findAllFunction)().then(function (e) {
                  var t = 0;
                  e.datalist.forEach(function (e, i) {
                    0 == i
                      ? ((e.data.checked = !0), (t = e.data.funcId))
                      : (e.data.checked = !1),
                      n.pfunction.push(e.data),
                      e.children.forEach(function (e, i) {
                        e.data.pFuncId == t
                          ? (e.data.show = !0)
                          : (e.data.show = !1),
                          (e.data.checked = !1),
                          (e.data.disabled = !1),
                          e.children &&
                            e.children.length > 0 &&
                            (e.data.children = e.children.map(function (e) {
                              return r(
                                r({}, e.data),
                                {},
                                { selectedValue: null, selected: !1 },
                              );
                            })),
                          n.cfunction.push(e.data);
                      });
                  }),
                    n.functionChecked();
                });
            },
            changePFunction: function (e) {
              var n = this;
              this.pfunction.forEach(function (n) {
                n.funcId == e.funcId ? (n.checked = !0) : (n.checked = !1);
              }),
                this.cfunction.forEach(function (t, i) {
                  t.pFuncId == e.funcId ? (t.show = !0) : (t.show = !1),
                    n.$set(n.cfunction, i, t);
                });
            },
            changeRole: function (e) {
              var n = this;
              this.permissionList.forEach(function (t, i) {
                e.roleId == t.roleId
                  ? ((t.checked = !0), n.$set(n.permissionList, i, t))
                  : ((t.checked = !1), n.$set(n.permissionList, i, t));
              }),
                this.functionChecked();
            },
            functionChecked: function () {
              var e = this;
              this.cfunction.forEach(function (n, t) {
                (n.checked = !1),
                  (n.disabled = !1),
                  n.children &&
                    (e.$set(n, "selectedRadioValue", null),
                    n.children.forEach(function (n) {
                      e.$set(n, "selected", !1);
                    }));
              }),
                this.permissionList.forEach(function (n) {
                  n.checked &&
                    e.cfunction.forEach(function (t, i) {
                      if (
                        (1 != n.isCustom
                          ? (t.disabled = !0)
                          : (t.disabled = !1),
                        (t.checked = !1),
                        t.children &&
                          (e.$set(t, "selectedRadioValue", null),
                          t.children.forEach(function (n) {
                            e.$set(n, "selected", !1);
                          })),
                        n.functionIds.filter(function (e) {
                          return t.funcId === e;
                        }).length > 0 && (t.checked = !0),
                        t.children)
                      ) {
                        var c = [];
                        if (
                          (n.functionIds.forEach(function (e) {
                            var n = t.children.find(function (n) {
                              return n.funcId === e;
                            });
                            n && (c.push(n), (t.checked = !0));
                          }),
                          c.length > 0)
                        )
                          if (51 === t.funcId)
                            c.forEach(function (n) {
                              e.$set(n, "selected", !0);
                            });
                          else {
                            var o = c[c.length - 1];
                            e.$set(t, "selectedRadioValue", o.funcId);
                          }
                      }
                    });
                });
            },
            parentSwitchChange: function (e) {
              var n = this;
              if (!e.checked && e.children)
                this.$set(e, "selectedRadioValue", null),
                  e.children.forEach(function (e) {
                    e.selected = !1;
                  });
              else if (e.children && e.children.length > 0)
                if (22 === e.funcId || 31 === e.funcId) {
                  if (!e.selectedRadioValue) {
                    var t = e.children[e.children.length - 1];
                    this.$set(e, "selectedRadioValue", t.funcId);
                  }
                } else if (51 === e.funcId) {
                  e.children.some(function (e) {
                    return e.selected;
                  }) ||
                    e.children.forEach(function (e) {
                      n.$set(e, "selected", !0);
                    });
                }
            },
            childRadioChange: function (e, n) {
              e.disabled ||
                (this.$set(e, "selectedRadioValue", n), this.$forceUpdate());
            },
            getChildImageSrc: function (e) {
              return (
                {
                  场馆信息: "/static/imgs/basics-setting-1.png",
                  会员卡: "/static/imgs/basics-setting-2.png",
                  "教练/员工": "/static/imgs/basics-setting-3.png",
                  课目管理: "/static/imgs/basics-setting-4.png",
                  关联卡与课: "/static/imgs/basics-setting-6.png",
                  "排课/课程": "/static/imgs/basics-setting-5.png",
                }[e] || ""
              );
            },
            toggleImageChild: function (e, n) {
              e.disabled ||
                (this.$set(n, "selected", !n.selected), this.$forceUpdate());
            },
            submit: function () {
              var n = this;
              this.permissionList.forEach(function (t) {
                if (t.checked)
                  if (1 == t.isCustom) {
                    var i = {};
                    if (
                      ((i.functionIds = n.cfunction
                        .filter(function (e) {
                          return e.checked;
                        })
                        .map(function (e) {
                          if (e.children) {
                            var n = [e.funcId];
                            e.selectedRadioValue &&
                              n.push(e.selectedRadioValue);
                            var t = e.children.filter(function (e) {
                              return e.selected;
                            });
                            return (
                              t.length > 0 &&
                                t.forEach(function (e) {
                                  e.selected && n.push(e.funcId);
                                }),
                              n
                            );
                          }
                          return e.funcId;
                        })
                        .flat()),
                      (i.functionIds = (0, c.default)(new Set(i.functionIds))),
                      (i.isCustom = 1),
                      (i.roleName = "自定义"),
                      (i.roleId = t.roleId),
                      (i.isAdmin = 0),
                      0 == i.functionIds.length)
                    )
                      return (
                        e.showToast({
                          title: "请选择至少一个功能",
                          icon: "none",
                        }),
                        !1
                      );
                    console.log("保存的功能ID列表:", i.functionIds),
                      (0, u.saveRole)(i).then(function (e) {
                        (i.roleId = e.roleId),
                          (t.functionIds = i.functionIds),
                          (t.roleId = e.roleId),
                          n.$emit("savePermissionList", n.permissionList, t),
                          (n.permissionShow = !1);
                      });
                  } else
                    n.$emit("savePermissionList", n.permissionList, t),
                      (n.permissionShow = !1);
              });
            },
          },
        };
        n.default = d;
      }).call(this, t("df3c").default);
    },
    cf7e: function (e, n, t) {
      "use strict";
      t.d(n, "b", function () {
        return c;
      }),
        t.d(n, "c", function () {
          return o;
        }),
        t.d(n, "a", function () {
          return i;
        });
      var i = {
          ffPopup: function () {
            return t
              .e("components/ff-popup/ff-popup")
              .then(t.bind(null, "c29b"));
          },
          uIcon: function () {
            return t
              .e("uview-ui/components/u-icon/u-icon")
              .then(t.bind(null, "81af"));
          },
          uSwitch: function () {
            return t
              .e("uview-ui/components/u-switch/u-switch")
              .then(t.bind(null, "a048"));
          },
          uLine: function () {
            return t
              .e("uview-ui/components/u-line/u-line")
              .then(t.bind(null, "fac3"));
          },
          uRadioGroup: function () {
            return Promise.all([
              t.e("common/vendor"),
              t.e("uview-ui/components/u-radio-group/u-radio-group"),
            ]).then(t.bind(null, "aed4"));
          },
          uRadio: function () {
            return t
              .e("uview-ui/components/u-radio/u-radio")
              .then(t.bind(null, "acf8"));
          },
          uButton: function () {
            return t
              .e("uview-ui/components/u-button/u-button")
              .then(t.bind(null, "d5d3"));
          },
        },
        c = function () {
          var e = this,
            n =
              (e.$createElement,
              e._self._c,
              e.imgsrc("/static/imgs/triangle.png")),
            t = e.__map(e.cfunction, function (n, t) {
              var i = e.__get_orig(n),
                c = n.show
                  ? n.checked && n.children && n.children.length > 0
                  : null,
                o = n.show
                  ? n.checked && n.children && n.children.length > 0
                  : null;
              return {
                $orig: i,
                g0: c,
                g1: o,
                l0:
                  n.show && o && 51 === n.funcId
                    ? e.__map(n.children, function (n, t) {
                        return {
                          $orig: e.__get_orig(n),
                          m1: e.imgsrc(e.getChildImageSrc(n.funcName)),
                        };
                      })
                    : null,
              };
            });
          e.$mp.data = Object.assign({}, { $root: { m0: n, l1: t } });
        },
        o = [];
    },
    d50b: function (e, n, t) {
      "use strict";
      t.r(n);
      var i = t("cf7e"),
        c = t("e411");
      for (var o in c)
        ["default"].indexOf(o) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return c[e];
            });
          })(o);
      t("e494");
      var u = t("828b"),
        s = Object(u.a)(
          c.default,
          i.b,
          i.c,
          !1,
          null,
          "6e33059b",
          null,
          !1,
          i.a,
          void 0,
        );
      n.default = s.exports;
    },
    db0e: function (e, n, t) {},
    e411: function (e, n, t) {
      "use strict";
      t.r(n);
      var i = t("af00"),
        c = t.n(i);
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return i[e];
            });
          })(o);
      n.default = c.a;
    },
    e494: function (e, n, t) {
      "use strict";
      var i = t("db0e");
      t.n(i).a;
    },
    ee40: function (e, n, t) {
      "use strict";
      (function (e, n) {
        var i = t("47a9");
        t("86d2"), i(t("3240"));
        var c = i(t("d50b"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = t), n(c.default);
      }).call(this, t("3223").default, t("df3c").createPage);
    },
  },
  [["ee40", "common/runtime", "common/vendor"]],
]);

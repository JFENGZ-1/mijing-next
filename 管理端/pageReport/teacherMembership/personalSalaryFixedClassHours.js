(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageReport/teacherMembership/personalSalaryFixedClassHours"],
  {
    "262f": function (t, e, i) {
      "use strict";
      var n = i("f9c8");
      i.n(n).a;
    },
    "7c15": function (t, e, i) {
      "use strict";
      (function (t) {
        var n = i("47a9");
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var s,
          o = n(i("7ca3")),
          r = i("4689"),
          c = {
            data: function () {
              return {
                courseName: !0,
                allSelected: !1,
                title: "课费设置",
                plist: [],
                tlist: [],
                user: {},
                custom_style: {
                  width: "458rpx",
                  height: "83rpx",
                  backgroundColor: "#FBD128",
                  fontSize: "32rpx",
                  borderRadius: "41rpx",
                  border: "none",
                  color: "#181818",
                },
              };
            },
            components: {
              navigation: function () {
                i.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(i("af9e"));
                    }.bind(null, i),
                  )
                  .catch(i.oe);
              },
              quickfill: function () {
                i.e("pageReport/component/quickfill")
                  .then(
                    function () {
                      return resolve(i("8a51"));
                    }.bind(null, i),
                  )
                  .catch(i.oe);
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
            methods:
              ((s = {
                quickfillSubmit: function (t) {
                  this.tlist.forEach(function (e) {
                    (e.selected = !0), (e.unitPrice = t + "元");
                  }),
                    this.$forceUpdate();
                },
                handlePrivateInputFocus: function (t) {
                  this.plist[t].unitPrice &&
                    (this.plist[t].unitPrice = this.plist[t].unitPrice.replace(
                      "元",
                      "",
                    ));
                },
                handlePrivateInputBlur: function (e) {
                  if (this.plist[e].unitPrice) {
                    var i = this.plist[e].unitPrice.replace("元", "");
                    if (!this.isPositiveInteger(i))
                      return (
                        t.showToast({
                          title: "".concat(
                            this.plist[e].courseName,
                            "的课费必须为正整数",
                          ),
                          icon: "none",
                        }),
                        void (this.plist[e].unitPrice = "")
                      );
                    this.plist[e].unitPrice = i + "元";
                  }
                  this.$forceUpdate();
                },
                handleAllSelect: function (t) {
                  this.tlist.forEach(function (e) {
                    e.selected = t.value;
                  });
                },
                handleInputFocus: function (t) {
                  this.tlist[t].unitPrice &&
                    (this.tlist[t].unitPrice = this.tlist[t].unitPrice.replace(
                      "元",
                      "",
                    ));
                },
                handleItemSelect: function (t) {
                  var e = this;
                  this.$nextTick(function () {
                    var t = e.tlist.every(function (t) {
                      return t.selected;
                    });
                    e.allSelected = t;
                  });
                },
                isPositiveInteger: function (t) {
                  return /^(0|[1-9]\d*)$/.test(t);
                },
                save: function () {
                  var e = this,
                    i = !0,
                    n = this.tlist.filter(function (n) {
                      if (n.selected) {
                        var s = n.unitPrice
                          ? n.unitPrice.replace("元", "")
                          : "";
                        return null != s && e.isPositiveInteger(s)
                          ? ((n.unitPrice = s), !0)
                          : (t.showToast({
                              title: "".concat(
                                n.courseName,
                                "的课费必须为正整数",
                              ),
                              icon: "none",
                            }),
                            (i = !1),
                            !1);
                      }
                      return !1;
                    }),
                    s = this.plist.filter(function (i) {
                      if (i.selected) {
                        var n = i.unitPrice
                          ? i.unitPrice.replace("元", "")
                          : "";
                        return n && e.isPositiveInteger(n)
                          ? ((i.unitPrice = n), !0)
                          : (t.showToast({
                              title: "".concat(
                                i.courseName,
                                "的课费必须为正整数",
                              ),
                              icon: "none",
                            }),
                            !1);
                      }
                      return !1;
                    });
                  if (i) {
                    if (0 === n.length && 0 === s.length)
                      return void t.showToast({
                        title: "请至少填写一个课程并设置课费",
                        icon: "none",
                      });
                    var o = {
                      staffUserid: this.user.staffUserid,
                      tlist: n,
                      plist: s,
                    };
                    (0, r.saveSalaryConfigOfOneStaff)(o).then(function (e) {
                      200 == e.code
                        ? t.showToast({
                            title: "保存成功",
                            icon: "none",
                            mask: !0,
                            success: function () {
                              setTimeout(function () {
                                t.navigateBack();
                              }, 500);
                            },
                          })
                        : t.showToast({ title: e.msg, icon: "none", mask: !0 });
                    });
                  }
                },
                handleInputBlur: function (e) {
                  if (this.tlist[e].unitPrice) {
                    var i = this.tlist[e].unitPrice.replace("元", "");
                    if (!this.isPositiveInteger(i))
                      return (
                        t.showToast({
                          title: "".concat(
                            this.tlist[e].courseName,
                            "的课费必须为正整数",
                          ),
                          icon: "none",
                        }),
                        (this.tlist[e].unitPrice = ""),
                        void (this.tlist[e].selected = !1)
                      );
                    (this.tlist[e].selected = !0),
                      (this.tlist[e].unitPrice = i + "元");
                  } else this.tlist[e].selected = !1;
                  this.$forceUpdate();
                },
              }),
              (0, o.default)(s, "handlePrivateInputBlur", function (e) {
                if (this.plist[e].unitPrice) {
                  var i = this.plist[e].unitPrice.replace("元", "");
                  if (!this.isPositiveInteger(i))
                    return (
                      t.showToast({
                        title: "".concat(
                          this.plist[e].courseName,
                          "的课费必须为正整数",
                        ),
                        icon: "none",
                      }),
                      void (this.plist[e].unitPrice = "")
                    );
                  this.plist[e].unitPrice = i + "元";
                }
                this.$forceUpdate();
              }),
              (0, o.default)(s, "getInit", function () {
                var t = this;
                (0, r.getSalaryConfigOfOneStaff)({
                  staffUserid: this.user.staffUserid,
                }).then(function (e) {
                  (t.plist = e.data.plist),
                    (t.tlist = e.data.tlist),
                    (t.user.staffFace = e.data.staffFace),
                    (t.user.staffName = e.data.staffName),
                    t.tlist.forEach(function (t, e) {
                      t.selected && (t.unitPrice = t.unitPrice + "元");
                    }),
                    (t.allSelected = t.tlist.every(function (t) {
                      return t.selected;
                    })),
                    t.plist.forEach(function (e, i) {
                      e.courseName ? (t.courseName = !0) : (t.courseName = !1),
                        e.selected && (e.unitPrice = e.unitPrice + "元");
                    });
                });
              }),
              s),
            watch: {
              plist: {
                handler: function (t) {
                  t.forEach(function (t) {
                    t.unitPrice ? (t.selected = !0) : (t.selected = !1);
                  });
                },
                deep: !0,
              },
            },
            onLoad: function (t) {
              (this.user.staffUserid = t.staffUserid), this.getInit();
            },
          };
        e.default = c;
      }).call(this, i("df3c").default);
    },
    "86d29": function (t, e, i) {
      "use strict";
      i.d(e, "b", function () {
        return s;
      }),
        i.d(e, "c", function () {
          return o;
        }),
        i.d(e, "a", function () {
          return n;
        });
      var n = {
          uButton: function () {
            return i
              .e("uview-ui/components/u-button/u-button")
              .then(i.bind(null, "d5d3"));
          },
          ffBottomLogo: function () {
            return i
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(i.bind(null, "3111"));
          },
        },
        s = function () {
          var t = this,
            e =
              (t.$createElement, t._self._c, t.$shorten(t.user.staffName, 15)),
            i = t.imgsrc("imgs/202506/kjtx.png"),
            n = t.__map(t.tlist, function (e, i) {
              return {
                $orig: t.__get_orig(e),
                m2: t.$shorten(e.courseName, 12),
                m3: e.tagData && e.tagData ? t.$shorten(e.tagData, 4) : null,
                m4: t.$shorten(e.staffName, 4),
              };
            }),
            s = t.plist && t.plist.length > 0;
          t._isMounted ||
            ((t.e0 = function (e) {
              return t.$refs.quickfillRef.open();
            }),
            (t.e1 = function (t) {
              t.stopPropagation();
            }),
            (t.e2 = function (t) {
              t.stopPropagation();
            }),
            (t.e3 = function (t) {
              t.stopPropagation();
            })),
            (t.$mp.data = Object.assign(
              {},
              { $root: { m0: e, m1: i, l0: n, g0: s } },
            ));
        },
        o = [];
    },
    a582: function (t, e, i) {
      "use strict";
      (function (t, e) {
        var n = i("47a9");
        i("86d2"), n(i("3240"));
        var s = n(i("b4dd"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = i), e(s.default);
      }).call(this, i("3223").default, i("df3c").createPage);
    },
    b4dd: function (t, e, i) {
      "use strict";
      i.r(e);
      var n = i("86d29"),
        s = i("d89d");
      for (var o in s)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            i.d(e, t, function () {
              return s[t];
            });
          })(o);
      i("262f");
      var r = i("828b"),
        c = Object(r.a)(
          s.default,
          n.b,
          n.c,
          !1,
          null,
          "44461769",
          null,
          !1,
          n.a,
          void 0,
        );
      e.default = c.exports;
    },
    d89d: function (t, e, i) {
      "use strict";
      i.r(e);
      var n = i("7c15"),
        s = i.n(n);
      for (var o in n)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            i.d(e, t, function () {
              return n[t];
            });
          })(o);
      e.default = s.a;
    },
    f9c8: function (t, e, i) {},
  },
  [["a582", "common/runtime", "common/vendor"]],
]);

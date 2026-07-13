(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageReport/teacherMembership/personalSalaryFixedPersonTime"],
  {
    "0037": function (t, i, e) {
      "use strict";
      (function (t) {
        var n = e("47a9");
        Object.defineProperty(i, "__esModule", { value: !0 }),
          (i.default = void 0);
        var s,
          r = n(e("7ca3")),
          o = n(e("3387")),
          a = e("4689"),
          c = {
            data: function () {
              return {
                courseName: !0,
                allSelected: !1,
                title: "课费提成设置",
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
                e.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(e("af9e"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
              quickfill: function () {
                e.e("pageReport/component/quickfillPersonTime")
                  .then(
                    function () {
                      return resolve(e("3962"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var i = t.getMenuButtonBoundingClientRect();
                return (
                  i.height +
                  2 * (i.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            methods:
              ((s = {
                quickfillSubmit: function (t) {
                  this.tlist.forEach(function (i) {
                    (i.selected = !0),
                      (i.unitPrice = t.unitPrice + "元"),
                      (i.exceedMan = t.exceedMan),
                      (i.additionalPrice = t.additionalPrice + "元");
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
                handlePrivateInputBlur: function (i) {
                  if (this.plist[i].unitPrice) {
                    var e = this.plist[i].unitPrice.replace("元", "");
                    if (!this.isPositiveInteger(e))
                      return (
                        t.showToast({
                          title: "".concat(
                            this.plist[i].courseName,
                            "的课费必须为正整数",
                          ),
                          icon: "none",
                        }),
                        void (this.plist[i].unitPrice = "")
                      );
                    this.plist[i].unitPrice = e + "元";
                  }
                  this.$forceUpdate();
                },
                handleAllSelect: function (t) {
                  this.tlist.forEach(function (i) {
                    i.selected = t.value;
                  });
                },
                handleInputFocus: function (t) {
                  this.tlist[t].unitPrice &&
                    (this.tlist[t].unitPrice = this.tlist[t].unitPrice.replace(
                      "元",
                      "",
                    ));
                },
                handleInputadditionalPriceFocus: function (t) {
                  this.tlist[t].additionalPrice &&
                    (this.tlist[t].additionalPrice = this.tlist[
                      t
                    ].additionalPrice.replace("元", ""));
                },
                handleItemSelect: function (t) {
                  var i = this;
                  this.$nextTick(function () {
                    var t = i.tlist.every(function (t) {
                      return t.selected;
                    });
                    i.allSelected = t;
                  });
                },
                isPositiveInteger: function (t) {
                  return /^(0|[1-9]\d*)$/.test(t);
                },
                save: function () {
                  var i = this,
                    e = !0,
                    n = this.tlist.find(function (t) {
                      if (t.selected) {
                        var n = t.unitPrice
                            ? t.unitPrice.replace("元", "")
                            : "",
                          s = t.exceedMan,
                          r = t.additionalPrice
                            ? t.additionalPrice.replace("元", "")
                            : "";
                        if (null == n || !i.isPositiveInteger(n))
                          return (
                            (t.title = "【".concat(
                              t.courseName,
                              "】的基础课费不能为空或负数",
                            )),
                            (e = !1),
                            !0
                          );
                        if (null == s || !i.isPositiveInteger(s))
                          return (
                            (t.title = "【".concat(
                              t.courseName,
                              "】的超过几人不能为空或负数",
                            )),
                            (e = !1),
                            !0
                          );
                        if (null == r || !i.isPositiveInteger(r))
                          return (
                            (t.title = "【".concat(
                              t.courseName,
                              "】的每人奖励不能为空或负数",
                            )),
                            (e = !1),
                            !0
                          );
                      }
                      return !1;
                    });
                  n && t.showToast({ title: n.title, icon: "none" });
                  var s = o.default.cloneDeep(this.tlist).filter(function (t) {
                      if (t.selected) {
                        var i = t.unitPrice
                            ? t.unitPrice.replace("元", "")
                            : "",
                          e = t.additionalPrice
                            ? t.additionalPrice.replace("元", "")
                            : "";
                        return (t.unitPrice = i), (t.additionalPrice = e), !0;
                      }
                      return !1;
                    }),
                    r = o.default.cloneDeep(this.plist).filter(function (e) {
                      if (e.selected) {
                        var n = e.unitPrice
                          ? e.unitPrice.replace("元", "")
                          : "";
                        return n && i.isPositiveInteger(n)
                          ? ((e.unitPrice = n), !0)
                          : (t.showToast({
                              title: "【".concat(
                                e.courseName,
                                "】的课费不能为空或负数",
                              ),
                              icon: "none",
                            }),
                            !1);
                      }
                      return !1;
                    });
                  if (e) {
                    if (0 === s.length && 0 === r.length)
                      return void t.showToast({
                        title: "请至少填写一个课程并设置课费",
                        icon: "none",
                      });
                    var c = {
                      staffUserid: this.user.staffUserid,
                      tlist: s,
                      plist: r,
                    };
                    (0, a.saveSalaryConfigOfOneStaff)(c).then(function (i) {
                      200 == i.code
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
                        : t.showToast({ title: i.msg, icon: "none", mask: !0 });
                    });
                  }
                },
                handleadditionalPriceInputBlur: function (i) {
                  if (this.tlist[i].additionalPrice) {
                    var e = this.tlist[i].additionalPrice.replace("元", "");
                    if (!this.isPositiveInteger(e))
                      return (
                        t.showToast({
                          title: "【".concat(
                            this.tlist[i].courseName,
                            "】的课费不能为空或负数",
                          ),
                          icon: "none",
                        }),
                        void (this.tlist[i].additionalPrice = "")
                      );
                    this.tlist[i].additionalPrice = e + "元";
                  }
                  this.$forceUpdate();
                },
                handleInputBlur: function (i) {
                  if (this.tlist[i].unitPrice) {
                    var e = this.tlist[i].unitPrice.replace("元", "");
                    if (!this.isPositiveInteger(e))
                      return (
                        t.showToast({
                          title: "【".concat(
                            this.tlist[i].courseName,
                            "】的课费不能为空或负数",
                          ),
                          icon: "none",
                        }),
                        (this.tlist[i].unitPrice = ""),
                        void (this.tlist[i].selected = !1)
                      );
                    (this.tlist[i].selected = !0),
                      (this.tlist[i].unitPrice = e + "元");
                  } else this.tlist[i].selected = !1;
                  this.$forceUpdate();
                },
              }),
              (0, r.default)(s, "handlePrivateInputBlur", function (i) {
                if (this.plist[i].unitPrice) {
                  var e = this.plist[i].unitPrice.replace("元", "");
                  if (!this.isPositiveInteger(e))
                    return (
                      t.showToast({
                        title: "【".concat(
                          this.plist[i].courseName,
                          "】的课费不能为空或负数",
                        ),
                        icon: "none",
                      }),
                      void (this.plist[i].unitPrice = "")
                    );
                  this.plist[i].unitPrice = e + "元";
                }
                this.$forceUpdate();
              }),
              (0, r.default)(s, "getInit", function () {
                var t = this;
                (0, a.getSalaryConfigOfOneStaff)({
                  staffUserid: this.user.staffUserid,
                }).then(function (i) {
                  (t.plist = i.data.plist),
                    (t.tlist = i.data.tlist),
                    (t.user.staffFace = i.data.staffFace),
                    (t.user.staffName = i.data.staffName),
                    t.tlist.forEach(function (t, i) {
                      t.selected &&
                        ((t.unitPrice = t.unitPrice + "元"),
                        (t.additionalPrice = t.additionalPrice + "元"));
                    }),
                    (t.allSelected = t.tlist.every(function (t) {
                      return t.selected;
                    })),
                    t.plist.forEach(function (i, e) {
                      i.courseName ? (t.courseName = !0) : (t.courseName = !1),
                        i.selected && (i.unitPrice = i.unitPrice + "元");
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
        i.default = c;
      }).call(this, e("df3c").default);
    },
    "0758": function (t, i, e) {
      "use strict";
      e.d(i, "b", function () {
        return s;
      }),
        e.d(i, "c", function () {
          return r;
        }),
        e.d(i, "a", function () {
          return n;
        });
      var n = {
          uButton: function () {
            return e
              .e("uview-ui/components/u-button/u-button")
              .then(e.bind(null, "d5d3"));
          },
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
        },
        s = function () {
          var t = this,
            i =
              (t.$createElement, t._self._c, t.$shorten(t.user.staffName, 15)),
            e = t.imgsrc("imgs/202506/kjtx.png"),
            n = t.__map(t.tlist, function (i, e) {
              return {
                $orig: t.__get_orig(i),
                m2: t.$shorten(i.courseName, 8),
                m3: i.tagData && i.tagData ? t.$shorten(i.tagData, 3) : null,
                m4: t.$shorten(i.staffName, 3),
              };
            }),
            s = t.plist && t.plist.length > 0;
          t._isMounted ||
            ((t.e0 = function (i) {
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
              { $root: { m0: i, m1: e, l0: n, g0: s } },
            ));
        },
        r = [];
    },
    "1bbf": function (t, i, e) {
      "use strict";
      e.r(i);
      var n = e("0037"),
        s = e.n(n);
      for (var r in n)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            e.d(i, t, function () {
              return n[t];
            });
          })(r);
      i.default = s.a;
    },
    7793: function (t, i, e) {
      "use strict";
      e.r(i);
      var n = e("0758"),
        s = e("1bbf");
      for (var r in s)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            e.d(i, t, function () {
              return s[t];
            });
          })(r);
      e("960b");
      var o = e("828b"),
        a = Object(o.a)(
          s.default,
          n.b,
          n.c,
          !1,
          null,
          "bbf4dd16",
          null,
          !1,
          n.a,
          void 0,
        );
      i.default = a.exports;
    },
    "7adf": function (t, i, e) {},
    "960b": function (t, i, e) {
      "use strict";
      var n = e("7adf");
      e.n(n).a;
    },
    ca0e: function (t, i, e) {
      "use strict";
      (function (t, i) {
        var n = e("47a9");
        e("86d2"), n(e("3240"));
        var s = n(e("7793"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), i(s.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
  },
  [["ca0e", "common/runtime", "common/vendor"]],
]);

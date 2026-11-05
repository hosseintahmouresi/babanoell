class Codevz_Circle_And_Dot {
  constructor() {
    this.root = document.body
    this.cursor = document.querySelector(".curzr")

    this.position = {
      distanceX: 0, 
      distanceY: 0,
      distance: 0,
      pointerX: 0,
      pointerY: 0,
    },
    this.previousPointerX = 0
    this.previousPointerY = 0
    this.angle = 0
    this.previousAngle = 0
    this.angleDisplace = 0
    this.degrees = 57.296
    this.cursorSize = 25
    this.fading = false

    this.color1 = ( this.cursor.getAttribute( 'data-color-1' ) || '#333' )
    this.color2 = ( this.cursor.getAttribute( 'data-color-2' ) || '#fff' )

    this.cursorStyle = {
      boxSizing: 'border-box',
      position: 'fixed',
      top: `${ (this.cursorSize+60) / -2 }px`,
      left: `${ this.cursorSize / -2 }px`,
      zIndex: '2147483647',
      width: `${ this.cursorSize }px`,
      height: `${ this.cursorSize }px`,
      backgroundColor: '#fff0',
      border: '2px solid ' + this.color1,
      borderRadius: '50%',
      boxShadow: '0 -20px 0 -9px #0000',
      transition: '300ms, transform 100ms',
      userSelect: 'none',
      pointerEvents: 'none'
    }

    this.init(this.cursor, this.cursorStyle)
  }

  init(el, style) {
    Object.assign(el.style, style)
    this.cursor.removeAttribute("hidden")
    
  }

  move(event) {
    this.previousPointerX = this.position.pointerX
    this.previousPointerY = this.position.pointerY
    this.position.pointerX = event.pageX + this.root.getBoundingClientRect().x
    this.position.pointerY = event.pageY + this.root.getBoundingClientRect().y
    this.position.distanceX = this.previousPointerX - this.position.pointerX
    this.position.distanceY = this.previousPointerY - this.position.pointerY
    this.distance = Math.sqrt(this.position.distanceY ** 2 + this.position.distanceX ** 2)

    if (event.target.localName === 'button' || 
        event.target.localName === 'a' || 
        event.target.onclick !== null ||
        event.target.className.includes('curzr-hover')) {
      this.hover()
    } else {
      this.hoverout()
    }

    this.cursor.style.transform = `translate3d(${this.position.pointerX}px, ${this.position.pointerY}px, 0)`

    this.rotate(this.position)
    this.fade(this.distance)
  }

  rotate(position) {
    let unsortedAngle = Math.atan(Math.abs(position.distanceY) / Math.abs(position.distanceX)) * this.degrees
    this.previousAngle = this.angle

    if (position.distanceX <= 0 && position.distanceY >= 0) {
      this.angle = 90 - unsortedAngle + 0
    } else if (position.distanceX < 0 && position.distanceY < 0) {
      this.angle = unsortedAngle + 90
    } else if (position.distanceX >= 0 && position.distanceY <= 0) {
      this.angle = 90 - unsortedAngle + 180
    } else if (position.distanceX > 0 && position.distanceY > 0) {
      this.angle = unsortedAngle + 270
    }

    if (isNaN(this.angle)) {
      this.angle = this.previousAngle
    } else {
      if (this.angle - this.previousAngle <= -270) {
        this.angleDisplace += 360 + this.angle - this.previousAngle
      } else if (this.angle - this.previousAngle >= 270) {
        this.angleDisplace += this.angle - this.previousAngle - 360
      } else {
        this.angleDisplace += this.angle - this.previousAngle
      }
    }
    this.cursor.style.transform += ` rotate(${this.angleDisplace}deg)`
  }

  hover() {
    this.cursor.style.border = '15px solid ' + this.color1
  }

  hoverout() {
    this.cursor.style.border = '2px solid ' + this.color1
  }

  fade(distance) {
    this.cursor.style.boxShadow = `0 ${-20 - distance}px 0 -9px ${this.color2}`
    if (!this.fading) {
      this.fading = true
      setTimeout(() => {
        this.cursor.style.boxShadow = '0 -20px 0 -9px #11192000'
        this.fading = false
      }, 200)
    }
  }

  click() {
    this.cursor.style.transform += ` scale(0.5)`
    setTimeout(() => {
      this.cursor.style.transform = this.cursor.style.transform.replace(` scale(0.5)`, '')
    }, 50)
  }

  remove() {
    this.cursor.remove()
  }
}

(() => {
  const cursor = new Codevz_Circle_And_Dot()
  if(!/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
    document.onmousemove = function (event) {
      cursor.move(event)
    }
    document.onclick = function () {
      cursor.click()
    }
  } else {
    cursor.remove()
  }
})()